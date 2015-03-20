<?php namespace Consolle\Utils;

use Consolle\Utils\TransportException;
use League\Flysystem\Exception;

class RemoteFilesystem
{
    private $bytesMax;
    private $fileUrl;
    private $fileName;
    private $retry;
    private $progress;
    private $lastProgress;
    private $options;
    private $lastHeaders;

    /**
     * @var \Closure
     */
    public $writeLog;

    /**
     * Constructor.
     * @param array $options The options
     */
    public function __construct(array $options = array())
    {
        $this->options = $options;
    }

    /**
     * Copy the remote file in local.
     *
     * @param string $fileUrl   The file URL
     * @param string $fileName  the local filename
     * @param boolean $progress  Display the progression
     * @param array $options   Additional context options
     *
     * @return bool true
     */
    public function copy($fileUrl, $fileName, $progress = true, $options = array())
    {
        return $this->get($fileUrl, $options, $fileName, $progress);
    }

    /**
     * Get the content.
     *
     * @param string $fileUrl   The file URL
     * @param boolean $progress  Display the progression
     * @param array $options   Additional context options
     *
     * @return bool|string The content
     */
    public function getContents($fileUrl, $progress = true, $options = array())
    {
        return $this->get($fileUrl, $options, null, $progress);
    }

    /**
     * Retrieve the options set in the constructor
     *
     * @return array Options
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Returns the headers of the last request
     *
     * @return array
     */
    public function getLastHeaders()
    {
        return $this->lastHeaders;
    }

    /**
     * Get file content or copy action.
     *
     * @param string $fileUrl           The file URL
     * @param array $additionalOptions context options
     * @param string $fileName          the local filename
     * @param boolean $progress          Display the progression
     *
     * @throws \Exception
     * @return bool|string
     */
    protected function get($fileUrl, $additionalOptions = array(), $fileName = null, $progress = true)
    {
        $this->bytesMax         = 0;
        $this->fileUrl          = $fileUrl;
        $this->fileName         = $fileName;
        $this->progress         = $progress;
        $this->lastProgress     = null;
        $this->lastHeaders      = array();


        $options = $this->getOptionsForUrl($additionalOptions);

        if (isset($options['http']))
            $options['http']['ignore_errors'] = true;

        $ctx = StreamContextFactory::getContext($fileUrl, $options, array('notification' => array($this, 'callbackGet')));

        if ($this->progress)
            $this->log("Downloading: <comment>connection...</comment>");

        $errorMessage = '';
        $errorCode    = 0;
        $result       = false;
        set_error_handler(function ($code, $msg) use (&$errorMessage) {
            if ($errorMessage) {
                $errorMessage .= "\n";
            }
            $errorMessage .= preg_replace('{^file_get_contents\(.*?\): }', '', $msg);
        });

        try
        {
            $result = file_get_contents($fileUrl, false, $ctx);
        }
        catch (\Exception $e)
        {
            if ($e instanceof TransportException && !empty($http_response_header[0])) {
                $e->setHeaders($http_response_header);
            }
            if ($e instanceof TransportException && $result !== false) {
                $e->setResponse($result);
            }
            $result = false;
        }

        if ($errorMessage && !ini_get('allow_url_fopen'))
        {
            $errorMessage = 'allow_url_fopen must be enabled in php.ini (' . $errorMessage . ')';
        }

        restore_error_handler();
        if (isset($e) && !$this->retry)
        {
            throw $e;
        }

        // fail 4xx and 5xx responses and capture the response
        if (!empty($http_response_header[0]) && preg_match('{^HTTP/\S+ ([45]\d\d)}i', $http_response_header[0], $match)) {
            $errorCode = $match[1];
            if (!$this->retry) {
                $e = new TransportException('The "' . $this->fileUrl . '" file could not be downloaded (' . $http_response_header[0] . ')', $errorCode);
                $e->setHeaders($http_response_header);
                $e->setResponse($result);
                throw $e;
            }
            $result = false;
        }

        // decode gzip
        if ($result && extension_loaded('zlib') && substr($fileUrl, 0, 4) === 'http') {
            $decode = false;
            foreach ($http_response_header as $header) {
                if (preg_match('{^content-encoding: *gzip *$}i', $header)) {
                    $decode = true;
                    continue;
                } elseif (preg_match('{^HTTP/}i', $header)) {
                    $decode = false;
                }
            }

            if ($decode) {
                if (version_compare(PHP_VERSION, '5.4.0', '>=')) {
                    $result = zlib_decode($result);
                } else {
                    // work around issue with gzuncompress & co that do not work with all gzip checksums
                    $result = file_get_contents('compress.zlib://data:application/octet-stream;base64,' . base64_encode($result));
                }

                if (!$result) {
                    throw new TransportException('Failed to decode zlib stream');
                }
            }
        }

        if ($this->progress && !$this->retry) {
            $this->log("    Downloading: <comment>100%</comment>");
        }

        // handle copy command if download was successful
        if (false !== $result && null !== $fileName) {
            if ('' === $result) {
                throw new TransportException('"' . $this->fileUrl . '" appears broken, and returned an empty 200 response');
            }

            $errorMessage = '';
            set_error_handler(function ($code, $msg) use (&$errorMessage) {
                if ($errorMessage) {
                    $errorMessage .= "\n";
                }
                $errorMessage .= preg_replace('{^file_put_contents\(.*?\): }', '', $msg);
            });
            $result = (bool)file_put_contents($fileName, $result);
            restore_error_handler();
            if (false === $result) {
                throw new TransportException('The "' . $this->fileUrl . '" file could not be written to ' . $fileName . ': ' . $errorMessage);
            }
        }

        if ($this->retry)
        {
            $this->retry = false;

            $result = $this->get($this->fileUrl, $additionalOptions, $this->fileName, $this->progress);

            return $result;
        }

        if (false === $result) {
            $e = new TransportException('The "' . $this->fileUrl . '" file could not be downloaded: ' . $errorMessage, $errorCode);
            if (!empty($http_response_header[0])) {
                $e->setHeaders($http_response_header);
            }

            throw $e;
        }

        if (!empty($http_response_header[0])) {
            $this->lastHeaders = $http_response_header;
        }

        return $result;
    }

    /**
     * Get notification action.
     *
     * @param  integer $notificationCode The notification code
     * @param  integer $severity         The severity level
     * @param  string $message          The message
     * @param  integer $messageCode      The message code
     * @param  integer $bytesTransferred The loaded size
     * @param  integer $bytesMax         The total size
     * @throws TransportException
     */
    protected function callbackGet($notificationCode, $severity, $message, $messageCode, $bytesTransferred, $bytesMax)
    {
        switch ($notificationCode) {
            case STREAM_NOTIFY_FAILURE:
            case STREAM_NOTIFY_AUTH_REQUIRED:
                if (401 === $messageCode) {
                    // Bail if the caller is going to handle authentication failures itself.
                    if (!$this->retryAuthFailure) {
                        break;
                    }

                    throw new Exception('Not implementante auth');
                    break;
                }
                break;

            case STREAM_NOTIFY_AUTH_RESULT:
                if (403 === $messageCode) {
                    throw new Exception('Not implementante auth');
                    break;
                }
                break;

            case STREAM_NOTIFY_FILE_SIZE_IS:
                if ($this->bytesMax < $bytesMax) {
                    $this->bytesMax = $bytesMax;
                }
                break;

            case STREAM_NOTIFY_PROGRESS:
                if ($this->bytesMax > 0 && $this->progress) {
                    $progression = 0;

                    if ($this->bytesMax > 0) {
                        $progression = round($bytesTransferred / $this->bytesMax * 100);
                    }

                    if ((0 === $progression % 5) && 100 !== $progression && $progression !== $this->lastProgress) {
                        $this->lastProgress = $progression;
                        $this->log("    Downloading: <comment>$progression%</comment>");
                    }
                }
                break;

            default:
                break;
        }
    }

    protected function getOptionsForUrl($additionalOptions)
    {
        if (defined('HHVM_VERSION')) {
            $phpVersion = 'HHVM ' . HHVM_VERSION;
        } else {
            $phpVersion = 'PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
        }

        $headers = array(
            sprintf(
                'User-Agent: Consolle/%s (%s; %s; %s)',
                'source',
                //Composer::VERSION === '@package_version@' ? 'source' : Composer::VERSION,
                php_uname('s'),
                php_uname('r'),
                $phpVersion
            )
        );

        if (extension_loaded('zlib')) {
            $headers[] = 'Accept-Encoding: gzip';
        }

        $options = array_replace_recursive($this->options, $additionalOptions);

        if (isset($options['http']['header']) && !is_array($options['http']['header'])) {
            $options['http']['header'] = explode("\r\n", trim($options['http']['header'], "\r\n"));
        }
        foreach ($headers as $header) {
            $options['http']['header'][] = $header;
        }

        return $options;
    }

    /**
     * @param $str
     * @return bool
     */
    protected function log($str)
    {
        if ($this->writeLog instanceof \Closure)
        {
            $log = $this->writeLog;
            $log($str);
            return true;
        }

        echo $str . "\r\n";
        return true;
    }
}
