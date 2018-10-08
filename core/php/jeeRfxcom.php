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
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";
if (!jeedom::apiAccess(init('apikey'), 'rfxcom')) {
	echo __('Vous n\'etes pas autorisé à effectuer cette action', __FILE__);
	die();
}
if (isset($_GET['test'])) {
	echo 'OK';
	die();
}
$result = json_decode(file_get_contents("php://input"), true);
if (!is_array($result)) {
	die();
}

$convert = array(
	'alarm' => 1,
	'motion' => 1,
	'normal' => 0,
	'no_motion' => 0,
	'no' => 0,
	'arm' => 1,
	'arm_away' => 1,
	'disarm' => 0,
	'light_1_on' => 1,
	'light_1_off' => 0,
	'light_2_on' => 1,
	'light_2_off' => 0,
	'normal_delayed' => 0,
	'end_panic' => 0,
	'panic' => 1,
	'alarm_delayed' => 1,
	'light_detected' => 1,
	'dark_detected' => 0,
	'normal_tamper' => 0,
	'normal_delayed_tamper' => 1,
	'alarm_tamper' => 1,
	'motion_tamper' => 1,
	'no_motion_tamper' => 0,
);
$convert_logicalid = array(
	'arm' => 'arm',
	'disarm' => 'arm',
	'arm_away' => 'arm',
	'light_1_on' => 'light_1',
	'light_1_off' => 'light_1',
	'light_2_on' => 'light_2',
	'light_2_off' => 'light_2',
	'light_2_on' => 'light_2',
	'light_2_off' => 'light_2',
	'panic' => 'panic',
	'end_panic' => 'panic',
);

if (isset($result['include_mode'])) {
	config::save('include_mode', $result['include_mode'], 'rfxcom');
	event::add('rfxcom::include_mode', array('state' => $result['include_mode']));
	die();
}

if (isset($result['devices'])) {
	foreach ($result['devices'] as $key => $datas) {
		if (!isset($datas['id'])) {
			if (isset($datas['housecode']) && isset($datas['unitcode'])) {
				$datas['id'] = $datas['housecode'] . $datas['unitcode'];
			} else {
				continue;
			}
		}
		$rfxcom = rfxcom::byLogicalId($datas['id'], 'rfxcom');
		if (!is_object($rfxcom)) {
			$rfxcom = rfxcom::createFromDef($datas);
			if (!is_object($rfxcom)) {
				log::add('rfxcom', 'debug', __('Aucun équipement trouvé pour : ', __FILE__) . secureXSS($datas['id']));
				continue;
			}
			event::add('jeedom::alert', array(
				'level' => 'warning',
				'page' => 'rfxcom',
				'message' => '',
			));
			event::add('rfxcom::includeDevice', $rfxcom->getId());
		}
		if (!$rfxcom->getIsEnable()) {
			continue;
		}
		if ($datas['packettype'] == 10 || $datas['packettype'] == 11 || $datas['packettype'] == 14 || $datas['packettype'] == 15 || $datas['packettype'] == 16 || $datas['packettype'] == 17 || $datas['packettype'] == 19) {
			$logicalId = (strpos($datas['command'], 'Group') !== false) ? 'gr' : 'bt';
			$logicalId .= (isset($datas['unitcode'])) ? $datas['unitcode'] : '1';
			if (!isset($datas['command'])) {
				$datas['command'] = 'On';
			}
			if ($datas['command'] == 'Group') {
				$value = $datas['dimlevel'];
			} else {
				if ($datas['command'] == 'On' || $datas['command'] == 'Group_On') {
					$value = (isset($datas['dimlevel'])) ? $datas['dimlevel'] : 1;
				} else {
					$value = 0;
				}
			}
			$cmd = $rfxcom->getCmd('info', $logicalId);
			if (!is_object($cmd)) {
				$cmd = new rfxcomCmd();
				$config = array(
					'name' => $logicalId,
					'type' => 'info',
					'subtype' => 'binary',
					'isVisible' => 1,
					'isHistorized' => 0,
					'logicalId' => $logicalId,
					'eqLogic_id' => $rfxcom->getId(),
				);
				utils::a2o($cmd, $config);
				$cmd->save();
			}
			if (in_array($datas['command'], array('On', 'Off'))) {
				$action = $rfxcom->getCmd('info', $datas['command']);
				if (!is_object($cmd)) {
					$action = new rfxcomCmd();
					$config = array(
						'name' => $datas['command'],
						'type' => 'action',
						'subtype' => 'other',
						'isVisible' => 1,
						'isHistorized' => 0,
						'logicalId' => $datas['raw'],
						'eqLogic_id' => $rfxcom->getId(),
						'configuration' => array(
							'updateCmdId' => $cmd->getId(),
							'updateCmdToValue' => (in_array($datas['command'], array('On'))) ? 1 : 0,
						),
					);
					utils::a2o($action, $config);
					$action->save();
				}
			}
			if (is_object($cmd)) {
				$cmd->event($value);
			}
			$logicalId = "toggle_bt" . $datas['unitcode'] . "_" . $datas['command'];
			$cmd = $rfxcom->getCmd('info', $logicalId);
			if (is_object($cmd) == 1) {
				$cmd->event($cmd->execCmd() == 0 ? 1 : 0);
			}
		}
		if (isset($datas['status']) && strpos($datas['status'], 'Batlow') !== false) {
			$cmd = $rfxcom->getCmd('info', 'battery');
			if (is_object($cmd)) {
				$cmd->event(0);
			}
			$rfxcom->batteryStatus(0);
			continue;
		}
		if (isset($datas['status']) && isset($convert_logicalid[strtolower(trim($datas['status']))])) {
			$datas[$convert_logicalid[strtolower(trim($datas['status']))]] = trim($datas['status']);
		}
		foreach ($rfxcom->getCmd('info') as $cmd) {
			$logicalId = $cmd->getLogicalId();
			if (isset($datas[$logicalId])) {
				if ($logicalId == 'battery') {
					$batteryLevel = $datas[$logicalId] * 10;
					if ($batteryLevel >= 90) {
						$batteryLevel = 100;
					}
					$cmd->event($batteryLevel);
					$rfxcom->batteryStatus($batteryLevel);
				} elseif ($logicalId == 'signal') {
					$cmd->event($datas[$logicalId]);
				} else {
					$value = trim($datas[$logicalId]);
					if ($value == '$') {
						continue;
					}
					if (isset($convert[strtolower($value)])) {
						$value = $convert[strtolower($value)];
					}
					$cmd->event($value);
				}
			}
		}
	}
}