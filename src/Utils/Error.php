<?php namespace Consolle\Utils;

class Error
{
    /**
     * @var \Illuminate\Translation\Translator
     */
    protected $lang;

    public function __construct((\Illuminate\Contracts\Foundation\Application $app)
    {
        $this->lang = $app['translator'];
    }

    /**
     * Gera uma excecao e retorna o Exception
     * @param $msg
     * @return \Exception
     */
    public function create($msg)
    {
        // Verificar se mensagem pode ser traduzida pelo sistema da Lang do Laravel
        if (is_string($msg) && ($this->lang->has($msg)))
            $msg = $this->lang->get($msg);

        // Se msg for um objeto ou array deve fazer um print_r
        if (is_object($msg) || is_array($msg))
            $msg = print_r($msg, true);

        $args    = func_get_args();
        $args[0] = $msg;
        $msg     = trim(call_user_func_array('sprintf', $args));

        $code = $args[count($args) - 1];

        if (is_numeric($code))
            return new \Exception($msg, $code);
        else
            return new \Exception($msg);
    }

    /**
     * Gera uma excecao
     * @param $msg
     * @throws mixed
     * @return null;
     */
    public function make($msg)
    {
        throw call_user_func_array(array($this, 'create'), func_get_args());
    }
}