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

class klf200 extends eqLogic {
	public function loadCmdFromConf($type) {
		if (!is_file(dirname(__FILE__) . '/../config/devices/' . $type . '.json')) {
			return;
		}
		$content = file_get_contents(dirname(__FILE__) . '/../config/devices/' . $type . '.json');
		if (!is_json($content)) {
			return;
		}
		$device = json_decode($content, true);
		if (!is_array($device) || !isset($device['commands'])) {
			return true;
		}
		foreach ($device['commands'] as $command) {
			$cmd = null;
			foreach ($this->getCmd() as $liste_cmd) {
				if ((isset($command['logicalId']) && $liste_cmd->getLogicalId() == $command['logicalId'])
				|| (isset($command['name']) && $liste_cmd->getName() == $command['name'])) {
					$cmd = $liste_cmd;
					break;
				}
			}
			if ($cmd == null || !is_object($cmd)) {
				$cmd = new klf200Cmd();
				$cmd->setEqLogic_id($this->getId());
				utils::a2o($cmd, $command);
				$cmd->save();
			}
		}
	}

	public static function cron() {
		klf200::refreshAll();
	}

	public static function dependancy_info() {
		$return = array();
		$return['progress_file'] = jeedom::getTmpFolder('klf200') . '/dependancy';
		$cmd = "pip3 list | grep pyvlx";
		exec($cmd, $output, $return_var);
		$cmd = "pip3 list | grep aiohttp";
		exec($cmd, $output2, $return_var);
		$return['state'] = 'nok';
		if (array_key_exists(0,$output) && array_key_exists(0,$output2)) {
		    if ($output[0] != "" && $output2[0] != "") {
			$return['state'] = 'ok';
		    }
		}
		return $return;
	}

	public static function dependancy_install() {
		$dep_info = self::dependancy_info();
		log::remove(__CLASS__ . '_dep');
		$resource_path = realpath(dirname(__FILE__) . '/../../resources');
		/*if ($dep_info['state'] != 'ok') {

			passthru('/bin/bash ' . $resource_path . '/install_apt.sh ' . jeedom::getTmpFolder('klf200') . '/dependancy > ' . log::getPathToLog(__CLASS__ . '_dep') . ' '. config::byKey('addr', 'klf200') . '  2>&1 &');
		} else {
			passthru('/bin/bash ' . $resource_path . '/install_apt_force.sh ' . jeedom::getTmpFolder('klf200') . '/dependancy > ' . log::getPathToLog(__CLASS__ . '_dep') . ' '. config::byKey('addr', 'klf200') . ' 2>&1 &');
		}*/
      passthru('/bin/bash ' . $resource_path . '/install.sh ' . jeedom::getTmpFolder('klf200') . '/dependancy > ' . log::getPathToLog(__CLASS__ . '_dep') . ' '. config::byKey('addr', 'klf200') . '  2>&1 &');
	}

	public static function deamon_info() {
		$return = array();
		$return['log'] = 'klf200';
		$return['state'] = 'nok';
		//$pid = trim( shell_exec ('ps ax | grep "klf200/resources/klf200d.py" | grep -v "grep" | wc -l') );
      $pid = trim( shell_exec ('ps ax | grep "klf200/resources/klf200d.js" | grep -v "grep" | wc -l') );
		if ($pid != '' && $pid != '0') {
			$return['state'] = 'ok';
		}
		$return['launchable'] = 'ok';
		if (config::byKey('addr', 'klf200', '') == '' || config::byKey('password', 'klf200', '') == '') {
			$return['launchable'] = 'nok';
		}
		return $return;
	}

	public static function deamon_start() {
		log::remove(__CLASS__ . '_update');
		log::remove(__CLASS__ . '_node');
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		$klf200_path = realpath(dirname(__FILE__) . '/../../resources/');
		//$cmd = '/usr/bin/python3 ' . $klf200_path . '/klf200d.py';
      $cmd = '/usr/bin/node ' . $klf200_path . '/klf200d.js';
		$cmd .= ' ' . config::byKey('addr', 'klf200');
		$cmd .= ' ' . config::byKey('password', 'klf200');
		log::add('klf200', 'info', 'Lancement démon klf200 : ' . $cmd);
		$result = exec($cmd . ' >> ' . log::getPathToLog('klf200') . ' 2>&1 &');
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
			log::add('klf200', 'error', 'Impossible de lancer le démon klf200d. Vérifiez le log.', 'unableStartDeamon');
			return false;
		}
		message::removeAll('klf200', 'unableStartDeamon');
		return true;
	}

	public static function deamon_stop() {
		//exec('kill $(ps aux | grep "/klf200d.py" | awk \'{print $2}\')');
      exec('kill $(ps aux | grep "/klf200d.js" | awk \'{print $2}\')');
		log::add('klf200', 'info', 'Arrêt du service klf200');
		$deamon_info = self::deamon_info();
		if ($deamon_info['state'] == 'ok') {
			sleep(1);
			//exec('kill -9 $(ps aux | grep "/klf200d.py" | awk \'{print $2}\')');
          exec('kill -9 $(ps aux | grep "/klf200d.js" | awk \'{print $2}\')');
		}
		$deamon_info = self::deamon_info();
		if ($deamon_info['state'] == 'ok') {
			sleep(1);
			//exec('sudo kill -9 $(ps aux | grep "/klf200d.py" | awk \'{print $2}\')');
          exec('sudo kill -9 $(ps aux | grep "/klf200d.js" | awk \'{print $2}\')');
		}
	}

	public function scanDevices() {
		$result = klf200::sendCommand('/devices');
		foreach ($result['devices'] as $klf200) {
			$eqlogic = klf200::byLogicalId($klf200['name'], 'klf200');
			if (!is_object($eqlogic)) {
				log::add('klf200', 'debug', 'Create ID : ' . $klf200['name']);
				$eqlogic = new klf200();
				$eqlogic->setEqType_name('klf200');
				$eqlogic->setLogicalId($klf200['name']);
				$eqlogic->setIsEnable(1);
				$eqlogic->setIsVisible(1);
				$eqlogic->setName($klf200['name']);
				$eqlogic->setConfiguration('id', $klf200['id']);
				$eqlogic->setConfiguration('name', $klf200['name']);
				$eqlogic->setConfiguration('type', $klf200['type']);
				$eqlogic->save();
			}
			if (klf200::isOpening($klf200['type'])) {
				if ($klf200['type'] == 'Window'){
					$eqlogic->loadCmdFromConf('window');
				} elseif ($klf200['type'] == 'Blind') {
					$eqlogic->loadCmdFromConf('blind');
				} else {
					$eqlogic->loadCmdFromConf('opening');
				}
			} else if ($klf200['type'] == "Light") {
				$eqlogic->loadCmdFromConf('light');
			} else {
				$eqlogic->loadCmdFromConf('switch');
			}
			//$eqlogic->refresh();
		}
		klf200::scanGlobal('Window');
		klf200::scanGlobal('Blind');
		klf200::scanGlobal('RollerShutter');
	}

	public function scanGlobal($_type = 'Window') {
		$eqLogics = self::byType('klf200', true);
		foreach ($eqLogics as $klf200) {
			if ($klf200->getConfiguration('type') == $_type) {
				$eqlogic = klf200::byLogicalId($_type, 'klf200');
				if (!is_object($eqlogic)) {
					log::add('klf200', 'debug', 'Create ID : ' . $_type . ' (for all ' . $_type . 's )');
					$eqlogic = new klf200();
					$eqlogic->setEqType_name('klf200');
					$eqlogic->setLogicalId($_type);
					$eqlogic->setIsEnable(1);
					$eqlogic->setIsVisible(1);
					$eqlogic->setName('All ' . $_type . 's');
					$eqlogic->setConfiguration('id', '99');
					$eqlogic->setConfiguration('name', 'All ' . $_type . 's');
					$eqlogic->setConfiguration('type', $_type);
					$eqlogic->save();
					$eqlogic->loadCmdFromConf('global');
				}
				return;
			}
		}
	}

	public function refresh() {
		if (klf200::isOpening($this->getConfiguration('type'))) {
			$result = klf200::sendCommand('/position/' . urlencode($this->getConfiguration('id')));
			$this->newPosition($result['position']);
		} else if ($this->getConfiguration('type') == "Light") {
			$result = klf200::sendCommand('/intensity/' . urlencode($this->getConfiguration('id')));
			$this->newIntensity($result['intensity']);
		} else {
			$result = klf200::sendCommand('/status/' . urlencode($this->getConfiguration('id')));
			$this->newStatus($result['status']);
		}
	}

	public function refreshAll() {
		$result = klf200::sendCommand('/devices');
		foreach ($result['devices'] as $klf200) {
			$eqlogic = klf200::byLogicalId($klf200['name'], 'klf200');
			if (is_object($eqlogic)) {
				if (klf200::isOpening($eqlogic->getConfiguration('type'))) {
					$eqlogic->newPosition($klf200['position']);
					if ($eqlogic->getConfiguration('type') == "Window") {
						$eqlogic->checkAndUpdateCmd('rain', $klf200['rain']);
					}
				} else if ($eqlogic->getConfiguration('type') == "Light") {
					$eqlogic->newIntensity($klf200['intensity']);
				} else {
					$eqlogic->newStatus($klf200['status']);
				}
			}
		}
	}

	public static function restartKlf() {
		$cmdOff = cmd::byId(str_replace('#','',config::byKey('powerOff', 'klf200', '')));
		if (is_object($cmdOff)) {
			$cmdOff->execute();
		}
		sleep(20);
		$cmdOn = cmd::byId(str_replace('#','',config::byKey('powerOn', 'klf200', '')));
		if (is_object($cmdOn)) {
			$cmdOn->execute();
		}
		sleep(20);
		self::deamon_start();
		log::add('klf200', 'error', 'KLF200 has been restarted after found not responding');
	}

	public static function isOpening($_type) {
		$result = false;
		$list = array("Window", "Blind", "RollerShutter", "GarageDoor", "Awning","Blade");
		if (in_array($_type,$list)) {
			$result = true;
		}
		return $result;
	}

	public function newPosition($_position) {
		log::add('klf200', 'debug', 'Update ' . $this->getName() . ' at ' . $_position . '%');
		$this->checkAndUpdateCmd('position', $_position);
		$this->checkAndUpdateCmd('positionOpen', 100 - $_position);
		if ($_position != '100') {
			$this->checkAndUpdateCmd('position_binary', 0);
		} else {
			$this->checkAndUpdateCmd('position_binary', 1);
		}
	}

	public function newStatus($_position) {
		log::add('klf200', 'debug', 'Update ' . $this->getName() . ' at ' . $_position . '%');
		$this->checkAndUpdateCmd('status', $_position);
	}

	public function newIntensity($_position) {
		log::add('klf200', 'debug', 'Update ' . $this->getName() . ' at ' . $_position . '%');
		$this->checkAndUpdateCmd('intensity', 100 - $_position);
		if ($_position == '100') {
			$this->checkAndUpdateCmd('intensity_binary', 0);
		} else {
			$this->checkAndUpdateCmd('intensity_binary', 1);
		}
	}

	public function sendAction($_action, $_value) {
		$uri = '/' . $_action . '/' . urlencode($this->getConfiguration('id'));
		if (($_action == 'set') || ($_action == 'lightset')) {
			$uri .= '/' . $_value;
		}
		log::add('klf200', 'debug', 'Action ' . $uri);
		$result = klf200::sendCommand($uri);
		//$this->refresh(); //not needed as just after action it doesn't have move
		if ($_action == 'stop') {
			$this->refresh();
		}
	}

	public static function sendCommand($_uri = '/devices') {
		if (config::byKey('addr', 'klf200', '') == '' || config::byKey('password', 'klf200', '') == '') {
			return;
		}
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			return;
		}
		$url = 'http://localhost:9123' . $_uri;
		$http = new com_http($url);
		$http->setNoReportError(true);
		log::add('klf200', 'debug', 'Send ' . $url);
		$return = $http->exec(8);
		log::add('klf200', 'debug', 'Result ' . $return);
		if ($return == '') {
			self::restartKlf();
		}
		return json_decode($return,true);
	}

}

class klf200Cmd extends cmd {
	public function execute($_options = null) {
		if ($this->getType() == 'action') {
			$eqLogic = $this->getEqLogic();
			if ($eqLogic->getConfiguration('id') == '99') {
				$eqLogics = klf200::byType('klf200', true);
				foreach ($eqLogics as $klf200) {
					if ($klf200->getConfiguration('id') == '99') {
						continue;
					}
					if ($klf200->getConfiguration('type') == $eqLogic->getConfiguration('type')) {
						if ($this->getLogicalId() == 'refresh') {
							$klf200->refresh();
						} else {
							if ($this->getLogicalId() == 'stop') {
								$value = 'stop';
							} elseif ($this->getSubType() == 'slider') {
								$value = $_options['slider'];
								if ($this->getLogicalId() == 'position_sliderOpen') {
									$value = 100 - $value;
								}
							} else {
								$value = $this->getConfiguration('value');
							}
							$klf200->sendAction($this->getConfiguration('action'),$value);
						}
					}
				}
			} else {
				if ($this->getLogicalId() == 'refresh') {
					$eqLogic->refresh();
				} else {
					if ($this->getSubType() == 'slider') {
						$value = $_options['slider'];
						if ($this->getLogicalId() == 'position_sliderOpen') {
							$value = 100 - $value;
						}
					} else {
						$value = $this->getConfiguration('value');
					}
					$eqLogic->sendAction($this->getConfiguration('action'),$value);
				}
			}
		}
	}
}
?>