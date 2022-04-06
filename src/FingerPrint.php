<?php
namespace OlimpusSoft;
use Yii;
use yii\base\Component;

/**
 * FingerPrint Clase para crear ua firma desde el request del cliente
 * Esto toma como base la ip del cliente, el navegador y su version
 * el server por donde se hace el consumo entre otros parametros 
 * esto con el fin de realizar una combinación de estos datos 
 * y convertirlos en un has con perdida usando los algoritmos 
 * de compresion con perdida sha1 y md5, cada que se ralice una solicitud
 * desde el cliente se tomaran estos datos y se crea la firma.
 *
 * @author Migue Angel Morales Coterio
 * @email miguelmoralescoterio@gmail.com
 * @date 10/12/2021
 */
class FingerPrint extends Component {
    
    public $getServerVariables = [
        "REDIRECT_HTTPS",
        "X-CLIENT",
        "HTTP_HOST",
        "HTTP_USER_AGENT",
        "HTTP_ACCEPT",
        "SERVER_SIGNATURE",
        "SERVER_SOFTWARE",
        "SERVER_NAME",
        "SERVER_ADDR",
        "SERVER_PORT",
        "REMOTE_ADDR",
        "REQUEST_SCHEME",
        "SERVER_PROTOCOL"
    ];

    public $fingerPrint = [];

    public function __invoke(array $serverVariables = null) {
        if($serverVariables && is_array($serverVariables)) {
            $this->getServerVariables = $serverVariables;
        }
        foreach ($this->getServerVariables as $variable) {
            $testVar = filter_input(INPUT_SERVER, $variable);
            if($testVar) {
                $this->fingerPrint[$variable] = $testVar;
            } else {
                $testVar = yii::$app->request->getHeaders()->get($variable);
                if($testVar) {
                    $this->fingerPrint[$variable] = $testVar;
                }
            }
        }
        return $this->fingerPrint;
    }

    public static function get(array $serverVariables = null) {
        $fingerPrint = new static($serverVariables);
        $textPrint = json_encode($fingerPrint());
        $textPrint = sha1($textPrint).'.'.md5($textPrint);
        $textPrint = strlen($textPrint) > 100 ? substr($textPrint, 0, 99) : $textPrint;
        return $textPrint;
    }

    /**
     * Metodo para el logs de la aplicacion
     * @param  String|Object|Array  $msg      Mensaje, objeto o arreglo a escribir en el log
     * @param  Numeric  $level    Nivel de Log LEVEL_INFO, LEVEL_WARNING, LEVEL_ERROR 
     * @param  string  $category Categoria para clasificar lugar del error
     * @param  integer $bq       Tabulacion de identacion
     */
    public function log($msg, $level=Logger::LEVEL_INFO, $category='application', $bq=0) {
        Logger::log($msg, $level, $category, $bq, false);
    }
}