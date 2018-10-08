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
if (!isConnect('admin')) {
	throw new Exception('401 Unauthorized');
}
include_file('3rdparty', 'jquery.tablesorter/theme.bootstrap', 'css');
include_file('3rdparty', 'jquery.tablesorter/jquery.tablesorter.min', 'js');
include_file('3rdparty', 'jquery.tablesorter/jquery.tablesorter.widgets.min', 'js');
sendVarToJs('manageProtocol_slaveId', init('slave_id'));
?>
<div id='div_rfxProtocoleAlert' style="display: none;"></div>
<div class="alert alert-info">{{Attention à ne pas activer tous les protocoles en même temps, certains sont incompatibles entre eux ou peuvent diminuer les performances d'autres protocoles}}</div>
<a class="btn btn-success pull-right" id="bt_saveRfxProtocole"><i class="fa fa-check-circle"></i> Enregistrer</a><br/><br/><br/>

<table id="table_rfxcomProtocole" class="table table-bordered table-condensed tablesorter">
    <thead>
        <tr>
            <th>{{Protocole}}</th>
            <th>{{Nom}}</th>
            <th>{{Actif}}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>0</td>
            <td>Undecoded</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::0" /></td>
        </tr>
        <tr>
            <td>1</td>
            <td>RFU (Opus XT300 /Imagintronix Soil sensor)</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::1" /></td>
        </tr>
        <tr>
            <td>2</td>
            <td>ByronSX	(Byron SXchime)</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::2" /></td>
        </tr>
        <tr>
            <td>3</td>
            <td>RSL (Conrad RSL2/Revolt NC5461)</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::3" /></td>
        </tr>
        <tr>
            <td>4</td>
            <td>Lighting4 (Brennenstuhl/ELRO AB400/Flamingo/Phenix/RisingSun/Sartano)</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::4" /></td>
        </tr>
        <tr>
            <td>5</td>
            <td>FineOffset (Viking- 02035, 02038, 02811/WT0122 pool sensor)</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::5" /></td>
        </tr>
        <tr>
            <td>6</td>
            <td>Rubicson (RUBiCSON stektermometer 48659, 48695)</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::6" /></td>
        </tr>
        <tr>
            <td>7</td>
            <td>AE (Blyss lighting)</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::7" /></td>
        </tr>
        <tr>
            <td>8</td>
            <td>BlindsT1 (A-OK blind motors/Ematronic/Hasta old blind motors/RAEX blind motor (YR1326 controlled))</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::8" /></td>
        </tr>
        <tr>
            <td>9</td>
            <td>BlindsT0 (BOFU blind motors/Hasta new blind motors/RollerTrol blind motors)</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::9" /></td>
        </tr>
        <tr>
            <td>10</td>
            <td>ProGuard</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::10" /></td>
        </tr>
        <tr>
            <td>11</td>
            <td>FS20</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::11" /></td>
        </tr>
        <tr>
            <td>12</td>
            <td>LaCrosse (Alecto – WS1200/La Crosse- TX2, TX3, TX3P, TX4, TX7, TX17, WS2300)</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::12" /></td>
        </tr>
        <tr>
            <td>13</td>
            <td>Hideki / UPM (Lexibook SM 1600)</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::13" /></td>
        </tr>
        <tr>
            <td>14</td>
            <td>AD (LightwaveRF/RGB LED strip driver dx.com/Siemens (UK))</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::14" /></td>
        </tr>
        <tr>
            <td>15</td>
            <td>Mertik (Mertik Maxitrol Fire Place controllers- G6R-H4T1, G6R-H4T ,G6R-H4TB, G6R-H4T21-Z22)</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::15" /></td>
        </tr>
        <tr>
            <td>16</td>
            <td>Visonic	(Visonic PowerCode)</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::16" /></td>
        </tr>
        <tr>
            <td>17</td>
            <td>ATI</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::17" /></td>
        </tr>
        <tr>
            <td>18</td>
            <td>Oregon (Alecto - SA30 smoke detector/Electrisave/cent-a-meter/Oregon Scientific / Huger BBQ and weather sensors,AW129, AW131, BTHGN129, BTHR918, BTHR918N, BTHR968,EW109, PCR800, RGR126, RGR682, RGR918, RGR928, RTGN318,RTGR328N, RTGR328N, RTGR368N, RTGR383, RTHN318, STR918,STR928,TGHN800, TGHN801, THC138, THC238, THC268,THGN122NX, THGN123N, THGN132ES, THGN132N, THGN500,THGR122(N/NX), THGR228(N/NF), THGR238, THGR268, THGR328N,THGR810, THGR918, THGR928, THGRN228NX, THN122N, THN132N,THR128, THR138, THR288(N/NF), THRN122N, THWR288A, THWR800,UV138, UVN128, UVN800, UVR128, WGR800, WGR918, WTGR800,WTGR800/Oregon Scientific weighting scales- BWR101, BWR102, GR101/OWL- CM113, CM180, CM119, CM160, CM180, CM180i)</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::18" /></td>
        </tr>
        <tr>
            <td>19</td>
            <td>Meiantech (Aidebao security/Atlantic security/Meiantech security)</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::19" /></td>
        </tr>
        <tr>
            <td>20</td>
            <td>HE EU (HomeEasy EU )</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::20" /></td>
        </tr>
        <tr>
            <td>21</td>
            <td>AC (ANSLUT /HomeEasy UK /Intertechno /Chacon/Coco/DIO /KlikAanKlikUit /NEXA )</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::21" /></td>
        </tr>
        <tr>
            <td>22</td>
            <td>ARC (ByeByeStandBy/ELRO AB600/HomeEasy UK /Chacon/Coco/DIO/DomiaLite /KlikAanKlikUit /NEXA )</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::22" /></td>
        </tr>
        <tr>
            <td>23</td>
            <td>X10 (Digimax/Ebode/Prego P-8426/RFXSensor/RFXMeter/Sunvic TLX1206/Sunvic TLX7506/X10 Ninja/Robocam/X10 PC REMOTE/X10 RTS10/X10 RFS10/X10 lighting/X10 security)</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::23" /></td>
        </tr>
        <tr>
            <td>24</td>
            <td>HomeConfort</td>
            <td><input type="checkbox" class="configKey" data-l1key="protocol::24" /></td>
        </tr>
    </tbody>
</table>

<script>
    initTableSorter();
    jeedom.config.load({
        configuration: $('#table_rfxcomProtocole').getValues('.configKey')[0],
        plugin: 'rfxcom',
        error: function (error) {
            $('#div_rfxProtocoleAlert').showAlert({message: error.message, level: 'danger'});
        },
        success: function (data) {
            $('#table_rfxcomProtocole').setValues(data, '.configKey');
            modifyWithoutSave = false;
        }
    });


    $("#bt_saveRfxProtocole").on('click', function (event) {
        $.hideAlert();
        jeedom.config.save({
            configuration: $('#table_rfxcomProtocole').getValues('.configKey')[0],
            plugin: 'rfxcom',
            error: function (error) {
                $('#div_rfxProtocoleAlert').showAlert({message: error.message, level: 'danger'});
            },
            success: function () {
                $('#div_rfxProtocoleAlert').showAlert({message: '{{Sauvegarde réussie. Le démon va être relancé}}', level: 'success'});
                jeedom.plugin.deamonStart({
                    id : 'rfxcom',
                    slave_id: 0,
                    forceRestart: 1,
                    error: function (error) {
                        $('#div_alert').showAlert({message: error.message, level: 'danger'});
                    },
                    success: function (data) {
                        $("#div_plugin_deamon").load('index.php?v=d&modal=plugin.deamon&plugin_id='+plugin_id);
                    }
                });
            }
        });
    });
</script>

