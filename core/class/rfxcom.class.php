<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class rfxcom extends eqLogic {
	/*     * *************************Attributs****************************** */

	/*     * ***********************Methode static*************************** */

	public static function createFromDef($_def) {
		event::add('jeedom::alert', array(
			'level' => 'warning',
			'page' => 'rfxcom',
			'message' => __('Nouveau module detecté', __FILE__),
		));
		sleep(2);
		$banId = explode(' ', config::byKey('banRfxId', 'rfxcom'));
		if (in_array($_def['id'], $banId)) {
			event::add('jeedom::alert', array(
				'level' => 'danger',
				'page' => 'rfxcom',
				'message' => __('Le module a un id banni. Inclusion impossible', __FILE__),
			));
			return false;
		}
		if (!isset($_def['packettype']) || !isset($_def['subtype']) || !isset($_def['id'])) {
			log::add('rfxcom', 'error', 'Information manquante pour ajouter l\'équipement : ' . print_r($_def, true));
			event::add('jeedom::alert', array(
				'level' => 'danger',
				'page' => 'rfxcom',
				'message' => __('Information manquante pour ajouter l\'équipement. Inclusion impossible', __FILE__),
			));
			return false;
		}
		$device = self::devicesParameters($_def['packettype']);

		if (count($device['subtype']) != 1 && !isset($device['subtype'][$_def['subtype']])) {
			log::add('rfxcom', 'info', 'Sous-type non trouvé : ' . print_r($_def, true) . ' dans : ' . print_r($device, true));
			event::add('jeedom::alert', array(
				'level' => 'danger',
				'page' => 'rfxcom',
				'message' => __('Aucun sous-type trouvé pour l\'équipement. Inclusion impossible', __FILE__),
			));
			return false;
		}
		$rfxcom = rfxcom::byLogicalId($_def['id'], 'rfxcom');
		if (!is_object($rfxcom)) {
			$eqLogic = new rfxcom();
			$eqLogic->setName($_def['id']);
		}
		$eqLogic->setLogicalId($_def['id']);
		$eqLogic->setEqType_name('rfxcom');
		$eqLogic->setIsEnable(1);
		$eqLogic->setIsVisible(1);
		$eqLogic->setConfiguration('device', $_def['packettype'] . '::' . $_def['subtype']);
		$model = $eqLogic->getModelList();
		if (count($model) > 0) {
			$eqLogic->setConfiguration('iconModel', array_keys($model)[0]);
		}
		$eqLogic->save();
		event::add('jeedom::alert', array(
			'level' => 'warning',
			'page' => 'rfxcom',
			'message' => __('Module inclu avec succès', __FILE__),
		));
		sleep(2);
		return $eqLogic;
	}

	public static function devicesParameters($_device = '') {
		$return = array();
		foreach (ls(dirname(__FILE__) . '/../config/devices', '*') as $dir) {
			$path = dirname(__FILE__) . '/../config/devices/' . $dir;
			if (!is_dir($path)) {
				continue;
			}
			$files = ls($path, '*.json', false, array('files', 'quiet'));
			foreach ($files as $file) {
				try {
					$content = file_get_contents($path . '/' . $file);
					if (is_json($content)) {
						$return += json_decode($content, true);
					}
				} catch (Exception $e) {

				}
			}
		}
		if (isset($_device) && $_device != '') {
			if (isset($return[$_device])) {
				return $return[$_device];
			}
			return array();
		}
		return $return;
	}

	public static function is_valid_image($name) {
		if (file_exists(dirname(__FILE__) . '/../config/devices/' . $name . '.jpg')) {
			return $name . '.jpg';
		} else if (file_exists(dirname(__FILE__) . '/../config/devices/' . substr($name, 0, strpos($name, '_')) . '.jpg')) {
			return substr($name, 0, strpos($name, '_')) . '.jpg';
		} else {
			return false;
		}
	}

	public static function dependancy_info() {
		$return = array();
		$return['progress_file'] = jeedom::getTmpFolder('rfxcom') . '/dependance';
		if (exec(system::getCmdSudo() . system::get('cmd_check') . '-E "python\-serial|python\-requests|python\-pyudev" | wc -l') >= 3) {
			$return['state'] = 'ok';
		} else {
			$return['state'] = 'nok';
		}
		return $return;
	}

	public static function dependancy_install() {
		log::remove(__CLASS__ . '_update');
		return array('script' => dirname(__FILE__) . '/../../resources/install_#stype#.sh ' . jeedom::getTmpFolder('rfxcom') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
	}

	public static function deamon_info() {
		$return = array();
		$return['log'] = 'rfxcom';
		$return['state'] = 'nok';
		$pid_file = jeedom::getTmpFolder('rfxcom') . '/deamon.pid';
		if (file_exists($pid_file)) {
			$pid = trim(file_get_contents($pid_file));
			if (is_numeric($pid) && posix_getsid($pid)) {
				$return['state'] = 'ok';
			} else {
				shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null;rm -rf ' . $pid_file . ' 2>&1 > /dev/null;');
			}
		}
		$return['launchable'] = 'ok';
		$port = config::byKey('port', 'rfxcom');
		if ($port != 'auto') {
			$port = jeedom::getUsbMapping($port);
			if (is_string($port)) {
				if (@!file_exists($port)) {
					$return['launchable'] = 'nok';
					$return['launchable_message'] = __('Le port n\'est pas configuré', __FILE__);
				}
				exec(system::getCmdSudo() . 'chmod 777 ' . $port . ' > /dev/null 2>&1');
			}
		}
		return $return;
	}

	public static function deamon_start() {
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		$port = config::byKey('port', 'rfxcom');
		if ($port != 'auto') {
			$port = jeedom::getUsbMapping($port);
		}
		$rfxcom_path = realpath(dirname(__FILE__) . '/../../resources/rfxcomd');
		$protocol = '';
		for ($i = 0; $i < 25; $i++) {
			if (config::byKey('protocol::' . $i, 'rfxcom')) {
				$protocol .= $i . ',';
			}
		}
		$protocol = trim($protocol, ',');
		$cmd = '/usr/bin/python ' . $rfxcom_path . '/rfxcomd.py';
		$cmd .= ' --device ' . $port;
		$cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel('rfxcom'));
		$cmd .= ' --socketport ' . config::byKey('socketport', 'rfxcom');
		$cmd .= ' --serialrate ' . config::byKey('serial_rate', 'rfxcom');
		$cmd .= ' --protocol ' . $protocol;
		$cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/rfxcom/core/php/jeeRfxcom.php';
		$cmd .= ' --apikey ' . jeedom::getApiKey('rfxcom');
		$cmd .= ' --cycle ' . config::byKey('cycle', 'rfxcom');
		$cmd .= ' --pid ' . jeedom::getTmpFolder('rfxcom') . '/deamon.pid';
		log::add('rfxcom', 'info', 'Lancement démon rfxcomd : ' . $cmd);
		exec($cmd . ' >> ' . log::getPathToLog('rfxcom') . ' 2>&1 &');
		$i = 0;
		while ($i < 30) {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				break;
			}
			sleep(1);
			$i++;
		}
		if ($i >= 30) {
			log::add('rfxcom', 'error', 'Impossible de lancer le démon RFXcom, vérifiez le log rfxcmd', 'unableStartDeamon');
			return false;
		}
		message::removeAll('rfxcom', 'unableStartDeamon');
		sleep(2);
		self::sendIdToDeamon();
		config::save('include_mode', 0, 'rfxcom');
		log::add('rfxcom', 'info', 'Démon RFXcom lancé');
		return true;
	}

	public static function deamon_stop() {
		$pid_file = jeedom::getTmpFolder('rfxcom') . '/deamon.pid';
		if (file_exists($pid_file)) {
			$pid = intval(trim(file_get_contents($pid_file)));
			system::kill($pid);
		}
		system::kill('rfxcomd.py');
		system::fuserk(config::byKey('socketport', 'rfxcom'));
		$port = config::byKey('port', 'rfxcom');
		if ($port != 'auto') {
			system::fuserk(jeedom::getUsbMapping($port));
		}
		sleep(1);
	}

	public static function sendIdToDeamon() {
		foreach (self::byType('rfxcom') as $eqLogic) {
			$eqLogic->allowDevice();
			usleep(300);
		}
	}

	public static function changeIncludeState($_state) {
		$value = json_encode(array('apikey' => jeedom::getApiKey('rfxcom'), 'cmd' => 'include_mode', 'state' => $_state));
		$socket = socket_create(AF_INET, SOCK_STREAM, 0);
		socket_connect($socket, '127.0.0.1', config::byKey('socketport', 'rfxcom'));
		socket_write($socket, $value, strlen($value));
		socket_close($socket);
	}

/*     * *********************Methode d'instance************************* */

	public function getModelList($_conf = '') {
		if ($_conf == '') {
			$_conf = $this->getConfiguration('device');
		}
		$_conf = explode('::', $_conf)[0];
		$modelList = array();
		$files = array();
		foreach (ls(dirname(__FILE__) . '/../config/devices', '*') as $dir) {
			if (!is_dir(dirname(__FILE__) . '/../config/devices/' . $dir)) {
				continue;
			}
			$files[$dir] = ls(dirname(__FILE__) . '/../config/devices/' . $dir, $_conf . '_*.jpg', false, array('files', 'quiet'));
			if (file_exists(dirname(__FILE__) . '/../config/devices/' . $dir . $_conf . '.jpg')) {
				$selected = 0;
				if ($dir . $_conf == $this->getConfiguration('iconModel')) {
					$selected = 1;
				}
				$modelList[$dir . $_conf] = array(
					'value' => __('Défaut', __FILE__),
					'selected' => $selected,
				);
			}
			if (count($files[$dir]) == 0) {
				unset($files[$dir]);
			}
		}
		$replace = array(
			$_conf => '',
			'.jpg' => '',
			'_' => ' ',
		);
		foreach ($files as $dir => $images) {
			foreach ($images as $imgname) {
				$selected = 0;
				if ($dir . str_replace('.jpg', '', $imgname) == $this->getConfiguration('iconModel')) {
					$selected = 1;
				}
				$modelList[$dir . str_replace('.jpg', '', $imgname)] = array(
					'value' => ucfirst(trim(str_replace(array_keys($replace), $replace, $imgname))),
					'selected' => $selected,
				);
			}
		}
		return $modelList;
	}

	public function getImage() {
		return 'plugins/rfxcom/core/config/devices/' . $this->getConfiguration('iconModel') . '.jpg';
	}

	public function preRemove() {
		$this->disallowDevice();
	}

	public function preInsert() {
		if ($this->getLogicalId() == '') {
			for ($i = 0; $i < 20; $i++) {
				$logicalId = strtoupper(str_pad(dechex(mt_rand()), 8, '0', STR_PAD_LEFT));
				$result = eqLogic::byLogicalId($logicalId, 'rfxcom');
				if (!is_object($result)) {
					$this->setLogicalId($logicalId);
					break;
				}
			}
			$this->allowDevice();
		}
	}

	public function postSave() {
		if ($this->getConfiguration('applyDevice') != $this->getConfiguration('device')) {
			$this->applyModuleConfiguration();
		} else {
			$this->allowDevice();
		}
	}

	public function allowDevice() {
		$value = array('apikey' => jeedom::getApiKey('rfxcom'), 'cmd' => 'add');
		$value['device'] = array(
			'id' => $this->getLogicalId(),
		);
		$value = json_encode($value);
		$socket = socket_create(AF_INET, SOCK_STREAM, 0);
		socket_connect($socket, '127.0.0.1', config::byKey('socketport', 'rfxcom'));
		socket_write($socket, $value, strlen($value));
		socket_close($socket);
	}

	public function disallowDevice() {
		if ($this->getLogicalId() == '') {
			return;
		}
		$value = json_encode(array('apikey' => jeedom::getApiKey('rfxcom'), 'cmd' => 'remove', 'device' => array('id' => $this->getLogicalId())));
		$socket = socket_create(AF_INET, SOCK_STREAM, 0);
		socket_connect($socket, '127.0.0.1', config::byKey('socketport', 'rfxcom'));
		socket_write($socket, $value, strlen($value));
		socket_close($socket);
	}

	public function applyModuleConfiguration() {
		$this->setConfiguration('applyDevice', $this->getConfiguration('device'));
		$this->save();
		if ($this->getConfiguration('device') == '') {
			return true;
		}
		$device_type = explode('::', $this->getConfiguration('device'));
		$packettype = $device_type[0];
		$subtype = $device_type[1];
		$device = self::devicesParameters($packettype);
		if (!is_array($device)) {
			return true;
		}
		if (isset($device['id_size']) && is_numeric($device['id_size']) && strlen($this->getLogicalId()) > $device['id_size']) {
			$this->setLogicalId(substr($this->getLogicalId(), 0, $device['id_size']));
		}
		if (!isset($device['subtype'][$subtype])) {
			if (count($device['subtype']) != 1) {
				return true;
			}
			$device = reset($device['subtype']);
		} else {
			$device = $device['subtype'][$subtype];
		}
		$this->import($device);
	}

/*     * **********************Getteur Setteur*************************** */
}

class rfxcomCmd extends cmd {
	/*     * *************************Attributs****************************** */

	/*     * ***********************Methode static*************************** */

	/*     * *********************Methode d'instance************************* */

	public function execute($_options = null) {
		if ($this->getType() != 'action') {
			return;
		}
		$value = '';
		$eqLogic = $this->getEqlogic();
		$device_type = explode('::', $eqLogic->getConfiguration('device'));
		if (isset($device_type[0])) {
			$path_file = dirname(__FILE__) . '/../config/devices/' . $device_type[0] . '/' . $device_type[0] . '.php';
			if (file_exists($path_file)) {
				require_once $path_file;
				$function = 'execCmd' . $device_type[0];
				if (function_exists($function)) {
					$value = $function($this, $_options);
					log::add('rfxcom', 'debug', 'Special function ' . $function . ' return  : ' . $value);
					if ($value === null) {
						return;
					}
				}
			}
		}
		if ($value == '') {
			$logicalId = ($this->getConfiguration('id') != '') ? $this->getConfiguration('id') : $eqLogic->getLogicalId();
			$value = $this->getLogicalId();
			if (preg_match("/.*chacon\(([0-9]*),([0-9]*)\).*/i", $value, $matches)) {
				$value = "0B11002F#ID##BTN##VALUE#0F70";
				$value = trim(str_replace("#BTN#", $matches[1], $value));
				$value = trim(str_replace("#VALUE#", $matches[2], $value));
			}
			$value = trim(str_replace("#ID#", $logicalId, $value));
			$value = trim(str_replace("#GROUP#", $this->getConfiguration('group'), $value));
			if (strpos($value, '#SeqNbr#') !== false) {
				$seqNbr = $this->getCache('SeqNbr', 17);
				if ($seqNbr > 255) {
					$seqNbr = 16;
				}
				$this->setCache('SeqNbr', $seqNbr + 1);
				$value = trim(str_replace("#SeqNbr#", dechex($seqNbr), $value));
			}
		}
		switch ($this->getSubType()) {
			case 'slider':
				$value = str_replace('#slider#', strtoupper(dechex(intval($_options['slider']))), $value);
				break;
			case 'color':
				$value = str_replace('#color#', $_options['color'], $value);
				break;
			case 'message':
				$value = str_replace('#message#', $_options['message'], $value);
				break;
		}
		$values = explode('&&', str_replace(' ', '', $value));
		$message = trim(json_encode(array('apikey' => jeedom::getApiKey('rfxcom'), 'cmd' => 'send', 'data' => $values)));
		$socket = socket_create(AF_INET, SOCK_STREAM, 0);
		socket_connect($socket, '127.0.0.1', config::byKey('socketport', 'rfxcom'));
		socket_write($socket, trim($message), strlen(trim($message)));
		socket_close($socket);
	}

	/*     * **********************Getteur Setteur*************************** */
}

?>
