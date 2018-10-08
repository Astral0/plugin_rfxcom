<?php
function execCmd143($_cmd, $_options) {
	$eqLogic = $_cmd->getEqLogic();
	if ($_cmd->getLogicalId() == 'bipon') {
		$eqLogic->setCache('previousValueBip', '01');
		return null;
	}
	if ($_cmd->getLogicalId() == 'bipoff') {
		$eqLogic->setCache('previousValueBip', '00');
		return null;
	}
	$device_type = explode('::', $eqLogic->getConfiguration('device'));
	$return = '0C43';
	$return .= isset($device_type[1]) ? $device_type[1] : '00';
	$return .= '02';
	$return .= $eqLogic->getLogicalId();
	$return .= $eqLogic->getCache('previousValueBip', '01');
	if ($_cmd->getLogicalId() == 'fan') {
		$eqLogic->setCache('previousValueFan', '0' . $_options['slider']);
	}
	$return .= $eqLogic->getCache('previousValueFan', '01');

	if ($_cmd->getLogicalId() == 'fan2') {
		$eqLogic->setCache('previousValueFan2', '0' . $_options['slider']);
	}
	$return .= $eqLogic->getCache('previousValueFan2', '01');

	if ($_cmd->getLogicalId() == 'flame') {
		$eqLogic->setCache('previousValueFlame', '0' . $_options['slider']);
	}
	$return .= $eqLogic->getCache('previousValueFlame', '01');

	if ($_cmd->getLogicalId() == 'off') {
		$eqLogic->setCache('previousValueCommand', '00');
	} else if ($_cmd->getLogicalId() == 'manual') {
		$eqLogic->setCache('previousValueCommand', '01');
	} else if ($_cmd->getLogicalId() == 'auto') {
		$eqLogic->setCache('previousValueCommand', '02');
	} else if ($_cmd->getLogicalId() == 'eco') {
		$eqLogic->setCache('previousValueCommand', '03');
	}
	$return .= $eqLogic->getCache('previousValueCommand', '00');
	$return .= '00';
	return $return;
}
?>