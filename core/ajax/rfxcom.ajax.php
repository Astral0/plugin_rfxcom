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

try {
	require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
	include_file('core', 'authentification', 'php');

	if (!isConnect('admin')) {
		throw new Exception('401 Unauthorized');
	}

	ajax::init();

	if (init('action') == 'changeIncludeState') {
		rfxcom::changeIncludeState(init('state'));
		ajax::success();
	}

	if (init('action') == 'changeBattery') {
		if (init('from') == init('to')) {
			throw new Exception(__('La source ne peut etre la cible', __FILE__));
		}
		$from = rfxcom::byId(init('from'));
		if (!is_object($from)) {
			throw new Exception(__('Equipement source non trouvé', __FILE__));
		}
		$to = rfxcom::byId(init('to'));
		if (!is_object($to)) {
			throw new Exception(__('Equipement cible non trouvé', __FILE__));
		}
		$to->setLogicalId($from->getLogicalId());
		$to->save();
		$from->remove();
		rfxcom::deamon_start();
		ajax::success();
	}

	if (init('action') == 'getModelList') {
		$rfxcom = rfxcom::byId(init('id'));
		if (!is_object($rfxcom)) {
			ajax::success(array());
		}
		ajax::success($rfxcom->getModelList(init('conf')));
	}

	throw new Exception('Aucune méthode correspondante');
	/*     * *********Catch exeption*************** */
} catch (Exception $e) {
	ajax::error(displayExeption($e), $e->getCode());
}
?>
