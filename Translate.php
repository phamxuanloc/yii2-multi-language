<?php
/**
 * Created by Navatech.
 * @project Yii2 Multi Language
 * @author  Phuong
 * @email   phuong17889[at]gmail.com
 * @date    04/02/2016
 * @time    11:03 SA
 * @version 1.0.0
 */
namespace navatech\language;

use navatech\language\models\Language as LanguageModel;
use navatech\language\models\Phrase;
use navatech\language\models\PhraseMeta;
use Yii;
use yii\helpers\Json;

class Translate {

	private $values;

	/**
	 * Language constructor.
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->values = $this->getData(Yii::$app->language);
	}

	/**
	 * @param $name
	 *
	 * @return string
	 */
	public function __get($name) {
		if(isset($this->values[$name])) {
			return $this->values[$name];
		} else {
			$model       = new Phrase();
			$model->name = $name;
			if($model->save()) {
				$phraseMeta              = new PhraseMeta();
				$phraseMeta->phrase_id   = $model->getPrimaryKey();
				$phraseMeta->language_id = LanguageModel::getIdByCode(Yii::$app->language);
				$phraseMeta->value       = "error: phrase [" . $name . "] not found";
				$phraseMeta->save();
			}
			return "error: phrase [" . $name . "] not found";
		}
	}

	/**
	 * @param $name
	 * @param $arguments
	 *
	 * @return string
	 */
	public static function __callStatic($name, $arguments) {
		$parameters = null;
		if(isset($arguments[0])) {
			$parameters = $arguments[0];
		}
		$language_code = Yii::$app->language;
		if(isset($arguments[1]) && is_string($arguments[1]) && strlen($arguments[1]) == 2) {
			$language_code = $arguments[1];
		}
		$language         = new Translate();
		$language->values = $language->getData($language_code);
		if($language->values != null && isset($language->values[$name]) && $value = $language->values[$name]) {
			if($parameters != null) {
				foreach($parameters as $key => $param) {
					$value = str_replace('{' . ($key + 1) . '}', $param, $value);
				}
			}
			return trim($value);
		} else {
			$model       = new Phrase();
			$model->name = $name;
			if($model->save()) {
				$phraseMeta              = new PhraseMeta();
				$phraseMeta->phrase_id   = $model->getPrimaryKey();
				$phraseMeta->language_id = LanguageModel::getIdByCode(Yii::$app->language);
				$phraseMeta->value       = "error: phrase [" . $name . "] not found";
				$phraseMeta->save();
			}
			return "error: phrase [" . $name . "] not found";
		}
	}

	/**
	 * @param null $code
	 *
	 * @return array|string
	 * @since 1.0.0
	 */
	public static function url($code = null) {
		if($code == null) {
			$code = Yii::$app->language;
		}
		$url = $_SERVER['REQUEST_URI'];
		if(is_int(strpos($url, 'language'))) {
			$url = explode("language", $url);
			$url = $url[0];
			$url .= 'language=' . $code;
		} else {
			if(is_int(strpos($url, '?'))) {
				$url .= '&language=' . $code;
			} else {
				$url .= '?language=' . $code;
			}
		}
		return $url;
	}

	/**
	 * @param $language_code
	 * @param $path
	 *
	 * @since 1.0.0
	 */
	private function _setData($language_code, $path) {
		$file = $path . DIRECTORY_SEPARATOR . 'phrase_' . $language_code . '.data';
		if(!file_exists($file)) {
			$fp = fopen($file, "wb");
			fwrite($fp, '');
			fclose($fp);
		}
		$data = file_get_contents($file);
		$data = Json::decode($data);
		if(empty($data) || $data == null) {
			/**@var $models Phrase[] */
			$models = Phrase::find()->all();
			$code   = $language_code;
			foreach($models as $model) {
				$model->setDynamicField();
				$data[$model->name] = $model->$code;
			}
		} else {
			/**@var $models Phrase[] */
			$models = Phrase::find()->all();
			$code   = $language_code;
			foreach($models as $model) {
				$model->setDynamicField();
				if(!isset($data[$model->name])) {
					$data[$model->name] = $model->$code;
				}
			}
		}
		file_put_contents($file, Json::encode($data));
	}

	/**
	 * @param $path
	 *
	 * @since 1.0.0
	 */
	private function _setClass($path) {
		$php = '<?php' . PHP_EOL;
		$php .= 'namespace navatech\language;' . PHP_EOL;
		$php .= 'class Translate {' . PHP_EOL;
		foreach($this->values as $key => $item) {
			$php .= '       /**' . PHP_EOL;
			$php .= '       * @param null $parameters' . PHP_EOL;
			$php .= '       * @param null $language_code' . PHP_EOL;
			$php .= '       * @return string' . PHP_EOL;
			$php .= '       */' . PHP_EOL;
			$php .= '       public static function ' . $key . '($parameters = null, $language_code = null){}' . PHP_EOL;
		}
		$php .= '}';
		$file = $path . DIRECTORY_SEPARATOR . 'Translate.php';
		$fp   = fopen($file, "wb");
		fwrite($fp, $php);
		fclose($fp);
	}

	/**
	 *
	 */
	public function setLanguage() {
		$runtime = Yii::getAlias('@runtime');
		$path    = $runtime . DIRECTORY_SEPARATOR . 'language';
		$code    = [];
		foreach(LanguageModel::getAllLanguages() as $language) {
			$code[$language->id] = $language->getAttributes();
		}
		$file = $path . DIRECTORY_SEPARATOR . 'language.data';
		$fp   = fopen($file, "wb");
		fwrite($fp, Json::encode($code));
		fclose($fp);
	}

	/**
	 * @param null $language_code
	 *
	 * @since 1.0.0
	 */
	public function setData($language_code = null) {
		$runtime = Yii::getAlias('@runtime');
		$path    = $runtime . DIRECTORY_SEPARATOR . 'language';
		if(!file_exists($path)) {
			mkdir($path, 0777, true);
		}
		if($language_code != null) {
			$this->_setData($language_code, $path);
		} else {
			foreach(LanguageModel::getAllLanguages() as $language) {
				$this->_setData($language->code, $path);
			}
		}
		$this->_setClass($path);
	}

	/**
	 * @param $language_code
	 *
	 * @return array|mixed|string
	 * @since 1.0.0
	 */
	public function getData($language_code) {
		$runtime = Yii::getAlias('@runtime');
		$path    = $runtime . DIRECTORY_SEPARATOR . 'language';
		if(!file_exists($path)) {
			return array();
		}
		$file = $path . DIRECTORY_SEPARATOR . 'phrase_' . $language_code . '.data';
		if(!file_exists($file)) {
			return array();
		}
		$data = file_get_contents($file);
		$data = Json::decode($data);
		return $data;
	}

	/**
	 * @param $language_code
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function removeData($language_code) {
		$runtime = Yii::getAlias('@runtime');
		$path    = $runtime . DIRECTORY_SEPARATOR . 'language';
		if(!file_exists($path)) {
			return true;
		}
		$file = $path . DIRECTORY_SEPARATOR . 'phrase_' . $language_code . '.data';
		if(!file_exists($file)) {
			return true;
		}
		return unlink($file);
	}
}