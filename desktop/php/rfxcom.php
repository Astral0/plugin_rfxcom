<?php
if (!isConnect('admin')) {
	throw new Exception('Error 401 Unauthorized');
}
$plugin = plugin::byId('rfxcom');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
function sortByOption($a, $b) {
	return strcmp($a['name'], $b['name']);
}
if (config::byKey('include_mode', 'rfxcom', 0) == 1) {
	echo '<div class="alert jqAlert alert-warning" id="div_inclusionAlert" style="margin : 0px 5px 15px 15px; padding : 7px 35px 7px 15px;">{{Vous etes en mode inclusion. Recliquez sur le bouton d\'inclusion pour sortir de ce mode}}</div>';
} else {
	echo '<div id="div_inclusionAlert"></div>';
}
?>

<div class="row row-overflow">
  <div class="col-lg-2 col-md-3 col-sm-4">
    <div class="bs-sidebar">
      <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
        <a class="btn btn-default eqLogicAction" style="width : 100%;margin-top : 5px;margin-bottom: 5px;" data-action="add"><i class="fa fa-plus-circle"></i> {{Ajouter}}</a>
        <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="Rechercher" style="width: 100%"/></li>
        <?php
foreach ($eqLogics as $eqLogic) {
	$opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
	echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '" style="' . $opacity . '"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
}
?>
     </ul>
   </div>
 </div>

 <div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
   <legend><i class="fa fa-cog"></i>  {{Gestion}}</legend>
   <div class="eqLogicThumbnailContainer">
    <div class="cursor eqLogicAction" data-action="add" style="background-color : #ffffff; height : 140px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
     <center>
      <i class="fa fa-plus-circle" style="font-size : 6em;color:#94ca02;"></i>
    </center>
    <span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#94ca02"><center>Ajouter</center></span>
  </div>
  <?php
if (config::byKey('include_mode', 'rfxcom', 0) == 1) {
	echo '<div class="cursor changeIncludeState include card" data-mode="1" data-state="0" style="background-color : #8000FF; height : 140px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >';
	echo '<center>';
	echo '<i class="fa fa-sign-in fa-rotate-90" style="font-size : 6em;color:#94ca02;"></i>';
	echo '</center>';
	echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#94ca02"><center>{{Arrêter inclusion}}</center></span>';
	echo '</div>';
} else {
	echo '<div class="cursor changeIncludeState include card" data-mode="1" data-state="1" style="background-color : #ffffff; height : 140px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >';
	echo '<center>';
	echo '<i class="fa fa-sign-in fa-rotate-90" style="font-size : 6em;color:#94ca02;"></i>';
	echo '</center>';
	echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#94ca02"><center>{{Mode inclusion}}</center></span>';
	echo '</div>';
}
?>
 <div class="cursor eqLogicAction" data-action="gotoPluginConf" style="background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;">
  <center>
    <i class="fa fa-wrench" style="font-size : 6em;color:#767676;"></i>
  </center>
  <span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#767676"><center>{{Configuration}}</center></span>
</div>
<div class="cursor" id="bt_healthrfx" style="background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
  <center>
    <i class="fa fa-medkit" style="font-size : 6em;color:#767676;"></i>
  </center>
  <span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#767676"><center>{{Santé}}</center></span>
</div>
</div>
<legend><i class="fa fa-table"></i>  {{Mes équipements RFXcom}}</legend>
<input class="form-control" placeholder="{{Rechercher}}" style="margin-bottom:4px;" id="in_searchEqlogic" />
<div class="eqLogicThumbnailContainer">
  <?php
foreach ($eqLogics as $eqLogic) {
	$device_id = substr($eqLogic->getConfiguration('device'), 0, strpos($eqLogic->getConfiguration('device'), ':'));
	$id_full = str_replace('::', '_', $eqLogic->getConfiguration('device'));
	$opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
	echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;' . $opacity . '" >';
	echo "<center>";
	$alternateImg = $eqLogic->getConfiguration('iconModel');
	if (file_exists(dirname(__FILE__) . '/../../core/config/devices/' . $alternateImg . '.jpg')) {
		echo '<img class="lazy" src="plugins/rfxcom/core/config/devices/' . $alternateImg . '.jpg" height="105" width="95" />';
	} elseif (file_exists(dirname(__FILE__) . '/../../core/config/devices/' . $eqLogic->getConfiguration('device') . '.jpg')) {
		echo '<img class="lazy" src="plugins/rfxcom/core/config/devices/' . $eqLogic->getConfiguration('device') . '.jpg" height="105" width="95" />';
	} else {
		echo '<img src="' . $plugin->getPathImgIcon() . '" height="105" width="95" />';
	}
	echo "</center>";
	echo '<span class="name" style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $eqLogic->getHumanName(true, true) . '</center></span>';
	echo '</div>';
}
?>
</div>
</div>

<div class="col-lg-10 col-md-9 col-sm-8 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
 <a class="btn btn-success eqLogicAction pull-right" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
 <a class="btn btn-danger eqLogicAction pull-right" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
 <a class="btn btn-default eqLogicAction pull-right" data-action="configure"><i class="fa fa-cogs"></i> {{Configuration avancée}}</a>
 <a class="btn btn-default eqLogicAction pull-right" data-action="copy"><i class="fa fa-files-o"></i> {{Dupliquer}}</a>
 <ul class="nav nav-tabs" role="tablist">
   <li role="presentation"><a class="eqLogicAction cursor" aria-controls="home" role="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
   <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i> {{Equipement}}</a></li>
   <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Commandes}}</a></li>
 </ul>
 <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
  <div role="tabpanel" class="tab-pane active" id="eqlogictab">
    <br/>
    <div class="row">
      <div class="col-sm-6">
        <form class="form-horizontal">
          <fieldset>
            <div class="form-group">
              <label class="col-sm-3 control-label">{{Nom de l'équipement RFXcom}}</label>
              <div class="col-sm-4">
                <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="Nom de l'équipement RFXcom"/>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-3 control-label">{{ID}}</label>
              <div class="col-sm-4">
                <input type="text" class="eqLogicAttr form-control" data-l1key="logicalId" placeholder="Logical ID"/>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-3 control-label"></label>
              <div class="col-sm-9">
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
              </div>
            </div>
            <div class="form-group">
              <label class="col-sm-3 control-label">{{Objet parent}}</label>
              <div class="col-sm-4">
                <select class="eqLogicAttr form-control" data-l1key="object_id">
                  <option value="">Aucun</option>
                  <?php
foreach (object::all() as $object) {
	echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
}
?>
               </select>
             </div>
           </div>
           <div class="form-group">
            <label class="col-sm-3 control-label">{{Catégorie}}</label>
            <div class="col-sm-9">
              <?php
foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
	echo '<label class="checkbox-inline">';
	echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
	echo '</label>';
}
?>

           </div>
         </div>
         <div class="form-group">
          <label class="col-sm-3 control-label"></label>
          <div class="col-sm-9">
            <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="noBatterieCheck"/>{{Ne pas verifier la batterie}}</label>
          </div>
        </div>
        <div class="form-group">
          <label class="col-sm-3 control-label">{{Délai maximum autorisé entre 2 messages (min)}}</label>
          <div class="col-sm-4">
            <input class="eqLogicAttr form-control" data-l1key="timeout" />
          </div>
        </div>
      </fieldset>
    </form>
  </div>
  <div class="col-sm-6">
    <form class="form-horizontal">
      <fieldset>
        <div class="form-group">
          <label class="col-sm-3 control-label">{{Changement de pile}}</label>
          <div class="col-sm-6">
            <a id="bt_changeBatteryTo" class="btn btn-default"><i class="fa fa-bolt"></i> {{Récupérer ID}}</a>
          </div>
        </div>
        <div class="form-group">
          <label class="col-sm-3 control-label">{{Equipement}}</label>
          <div class="col-sm-6">
            <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="device">
              <option value="">Aucun</option>
              <?php
$actuators = array();
$sensors = array();

foreach (rfxcom::devicesParameters() as $packettype => $info) {
	if (isset($info['actuator']) && $info['actuator'] == 1) {
		$actuators[$packettype] = $info;
	} else {
		$sensors[$packettype] = $info;
	}
}
uasort($sensors, 'sortByOption');
uasort($actuators, 'sortByOption');
echo '<optgroup label="{{Actionneur}}">';
foreach ($actuators as $packettype => $info) {
	$instruction = '';
	if (isset($info['instruction'])) {
		$instruction = $info['instruction'];
	}
	foreach ($info['subtype'] as $subtype => $subInfo) {
		echo '<option value="' . $packettype . '::' . $subtype . '" data-instruction="' . $instruction . '">' . $info['name'] . ' - ' . $subInfo['name'] . '</option>';
	}
}
echo '</optgroup>';
echo '<optgroup label="{{Capteur}}">';
foreach ($sensors as $packettype => $info) {
	$instruction = '';
	if (isset($info['instruction'])) {
		$instruction = $info['instruction'];
	}
	foreach ($info['subtype'] as $subtype => $subInfo) {
		echo '<option value="' . $packettype . '::' . $subtype . '" data-instruction="' . $instruction . '">' . $info['name'] . ' - ' . $subInfo['name'] . '</option>';
	}
}
echo '</optgroup>';
?>
      </select>
    </div>
  </div>
  <div class="form-group modelList" style="display:none;">
    <label class="col-sm-3 control-label">{{Modèle}}</label>
    <div class="col-sm-6">
     <select class="eqLogicAttr form-control listModel" data-l1key="configuration" data-l2key="iconModel">
     </select>
   </div>
 </div>
 <div id="div_instruction"></div>
 <div class="form-group">
  <label class="col-sm-3 control-label">{{Création}}</label>
  <div class="col-sm-3">
    <span class="eqLogicAttr label label-default" data-l1key="configuration" data-l2key="createtime" title="{{Date de création de l'équipement}}" style="font-size : 1em;cursor : default;"></span>
  </div>
  <label class="col-sm-3 control-label">{{Communication}}</label>
  <div class="col-sm-3">
    <span class="eqLogicAttr label label-default" data-l1key="status" data-l2key="lastCommunication" title="{{Date de dernière communication}}" style="font-size : 1em;cursor : default;"></span>
  </div>
</div>
<div class="form-group">
  <label class="col-sm-3 control-label hasBatterie">{{Batterie}}</label>
  <div class="col-sm-3 hasBatterie">
    <span class="eqLogicAttr label label-default" style="font-size : 1em;cursor : default;" data-l1key="status" data-l2key="battery"></span> %
  </div>
</div>
<center>
  <img src="core/img/no_image.gif" data-original=".jpg" id="img_device" class="img-responsive" style="max-height : 250px;"  onerror="this.src='plugins/rfxcom/plugin_info/rfxcom_icon.png'"/>
</center>
</fieldset>
</form>
</div>
</div>
</div>
<div role="tabpanel" class="tab-pane" id="commandtab">

  <a class="btn btn-success btn-sm cmdAction pull-right" data-action="add" style="margin-top:5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une commande}}</a><br/><br/>
  <table id="table_cmd" class="table table-bordered table-condensed">
    <thead>
      <tr>
        <th style="width: 300px;">{{Nom}}</th>
        <th style="width: 130px;">{{Type}}</th>
        <th>{{Logical ID (info) ou Commande brute (action)}}</th>
        <th >{{Paramètres}}</th>
        <th style="width: 100px;">{{Options}}</th>
        <th></th>
      </tr>
    </thead>
    <tbody>

    </tbody>
  </table>

</div>
</div>

</div>
</div>

<div title="{{Changement de batterie}}" id="md_changeBattery">
  <div class="alert alert-danger">
    {{Attention le changement de batterie va copier l'ID de votre equipement source vers l'ID de votre équipement cible. Et surtout <strong>SUPPRIMER</strong> votre équipement source}}
  </div>
  <form class="form-horizontal">
    <fieldset>
      <div class="form-group">
        <label class="col-sm-3 control-label">{{Source}}</label>
        <div class="col-sm-3">
          <span id="span_eqLogicFromName" class="label label-primary"></span>
          <span id="span_eqLogicFromId" style="display : none;"></span>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-3 control-label">{{Cible}}</label>
        <div class="col-sm-7">
         <select id="sel_eqLogicToId" class="form-control">
           <option value="">{{Aucune}}</option>
           <?php
foreach (eqLogic::byType('rfxcom') as $eqLogic) {
	echo '<option value="' . $eqLogic->getId() . '">' . $eqLogic->getHumanName() . '</option>';
}
?>
         </select>
       </div>
     </div>

   </fieldset>
 </form>
 <a class="btn btn-success pull-right" id="bt_valideChangeBattery"><i class="fa fa-check"></i> {{Valider}}</a>
 <a class="btn btn-danger pull-right" id="bt_cancelChangeBattery"><i class="fa fa-times"></i> {{Annuler}}</a>
</div>

<?php include_file('desktop', 'rfxcom', 'js', 'rfxcom');?>
<?php include_file('core', 'plugin.template', 'js');?>
