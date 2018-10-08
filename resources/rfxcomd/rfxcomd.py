# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
# 
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.

import globals
import logging
import string
import sys
import os
import time
import argparse
import datetime
import binascii
import re
import signal
import traceback
import xml.dom.minidom as minidom
from optparse import OptionParser
from os.path import join
import json

try:
	from jeedom.jeedom import *
except ImportError:
	print "Error: importing module jeedom.jeedom"
	sys.exit(1)

with open(os.path.dirname(__file__)+'/decode.json') as data_file:    
    RFXCOM_DECODE = json.load(data_file)

def decodeTemperature(message_high, message_low):
	temp_high = jeedom_utils.ByteToHex(message_high)
	temp_low = jeedom_utils.ByteToHex(message_low)
	polarity = jeedom_utils.testBit(int(temp_high,16),7)
	if polarity == 128:
		polarity_sign = "-"
	else:
		polarity_sign = ""
	temp_high = jeedom_utils.clearBit(int(temp_high,16),7)
	temp_high = temp_high << 8
	temperature = (temp_high + int(temp_low,16)) * 0.1
	temperature_str = polarity_sign + str(temperature)
	return temperature_str

# ----------------------------------------------------------------------------

def decodeSignal(message):
	signal = int(jeedom_utils.ByteToHex(message),16) >> 4
	return signal

# ----------------------------------------------------------------------------

def decodeBattery(message):
	battery = int(jeedom_utils.ByteToHex(message),16) & 0xf
	return battery

# ----------------------------------------------------------------------------

def decodePower(message_1, message_2, message_3):
	power_1 = jeedom_utils.ByteToHex(message_1)
	power_2 = jeedom_utils.ByteToHex(message_2)
	power_3 = jeedom_utils.ByteToHex(message_3)
	power_1 = int(power_1,16)
	power_1 = power_1 << 16
	power_2 = int(power_2,16) << 8
	power_3 = int(power_3,16)
	power = ( power_1 + power_2 + power_3)
	power_str = str(power)
	return power_str

def decodePacket(message):
	global RFXCOM_DECODE
	logging.debug("Decode : " + str(jeedom_utils.ByteToHex(message)))
	if not test_rfxcom( jeedom_utils.ByteToHex(message) ):
		logging.error("The incoming message is invalid (" + jeedom_utils.ByteToHex(message) + ")")
	raw_message = jeedom_utils.ByteToHex(message)
	raw_message = raw_message.replace(' ', '')
	packettype = jeedom_utils.ByteToHex(message[1])
	subtype = 0
	logging.debug("PacketType: %s" % str(packettype))
	if len(message) > 2:
		subtype = jeedom_utils.ByteToHex(message[2])
		logging.debug("SubType: %s" % str(subtype))
	if len(message) > 3:
		seqnbr = jeedom_utils.ByteToHex(message[3])
		logging.debug("SeqNbr: %s" % str(seqnbr))
	if len(message) > 4:
		id1 = jeedom_utils.ByteToHex(message[4])
		logging.debug("Id1: %s" % str(id1))
	if len(message) > 5:
		id2 = jeedom_utils.ByteToHex(message[5])
		logging.debug("Id2: %s" % str(id2))
	# ---------------------------------------
	# Verify correct length on packets
	# ---------------------------------------
	if packettype in RFXCOM_DECODE and 'lenght' in RFXCOM_DECODE[packettype] and len(message) <> RFXCOM_DECODE[packettype]['lenght']:
		logging.error("Packet has wrong length, discarding : "+str(len(message))+' <> '+str(RFXCOM_DECODE[packettype]['lenght']))
		return

	action = {'packettype' : str(packettype), 'subtype' : str(subtype), 'raw' : str(raw_message)}
	# ---------------------------------------
	# 0x01 - Interface Message
	# ---------------------------------------
	if packettype == '01':
		data = {
		'packetlen' : jeedom_utils.ByteToHex(message[0]),
		'packettype' : jeedom_utils.ByteToHex(message[1]),
		'subtype' : jeedom_utils.ByteToHex(message[2]),
		'seqnbr' : jeedom_utils.ByteToHex(message[3]),
		'cmnd' : jeedom_utils.ByteToHex(message[4]),
		'msg1' : jeedom_utils.ByteToHex(message[5]),
		'msg2' : jeedom_utils.ByteToHex(message[6]),
		'msg3' : jeedom_utils.ByteToHex(message[7]),
		'msg4' : jeedom_utils.ByteToHex(message[8]),
		'msg5' : jeedom_utils.ByteToHex(message[9]),
		'msg6' : jeedom_utils.ByteToHex(message[10]),
		'msg7' : jeedom_utils.ByteToHex(message[11]),
		'msg8' : jeedom_utils.ByteToHex(message[12]),
		'msg9' : jeedom_utils.ByteToHex(message[13])
		}
		if data['subtype'] == '00':
			logging.debug("Subtype\t\t\t= Interface response")
		elif data['subtype'] == '07':
			logging.debug("Subtype\t\t\t= Interface response")
			result =''
			for x in message[5:]:
				result = result +binascii.unhexlify(jeedom_utils.ByteToHex(x))
			logging.debug('Response is ' + result)
			globals.STATUS_PENDING = result
			return
		else:
			logging.debug("Subtype\t\t\t= Unknown type (" + data['packettype'] + ")")
		logging.debug("Sequence nbr\t\t= " + data['seqnbr'])
		logging.debug("Response on cmnd\t= " + RFXCOM_DECODE['cmd'][data['cmnd']])
		logging.debug("Transceiver type\t= " + RFXCOM_DECODE['packettype']['01']['msg']['1'][data['msg1']])
		logging.debug("Firmware version\t= " + str(int(data['msg2'],16)))
		globals.DEVICE_FIRMWARE = int(data['msg2'],16)
		if len(message)>14:
			globals.DEVICE_TYPE = int(jeedom_utils.ByteToHex(message[14]),16)
		logging.debug("Protocols:")
		if jeedom_utils.testBit(int(data['msg3'],16),7) == 128:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['128']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['128']))

		if jeedom_utils.testBit(int(data['msg3'],16),6) == 64:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['64']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['64']))

		if jeedom_utils.testBit(int(data['msg3'],16),5) == 32:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['32']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['32']))

		if jeedom_utils.testBit(int(data['msg3'],16),4) == 16:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['16']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['16']))

		if jeedom_utils.testBit(int(data['msg3'],16),3) == 8:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['8']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['8']))

		if jeedom_utils.testBit(int(data['msg3'],16),2) == 4:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['4']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['4']))

		if jeedom_utils.testBit(int(data['msg3'],16),1) == 2:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['2']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['2']))

		if jeedom_utils.testBit(int(data['msg3'],16),0) == 1:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['1']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['3']['1']))

		if jeedom_utils.testBit(int(data['msg4'],16),7) == 128:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['128']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['128']))

		if jeedom_utils.testBit(int(data['msg4'],16),6) == 64:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['64']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['64']))

		if jeedom_utils.testBit(int(data['msg4'],16),5) == 32:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['32']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['32']))

		if jeedom_utils.testBit(int(data['msg4'],16),4) == 16:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['16']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['16']))

		if jeedom_utils.testBit(int(data['msg4'],16),3) == 8:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['8']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['8']))

		if jeedom_utils.testBit(int(data['msg4'],16),2) == 4:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['4']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['4']))

		if jeedom_utils.testBit(int(data['msg4'],16),1) == 2:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['2']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['2']))

		if jeedom_utils.testBit(int(data['msg4'],16),0) == 1:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['1']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['4']['1']))

		if jeedom_utils.testBit(int(data['msg5'],16),7) == 128:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['128']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['128']))

		if jeedom_utils.testBit(int(data['msg5'],16),6) == 64:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['64']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['64']))

		if jeedom_utils.testBit(int(data['msg5'],16),5) == 32:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['32']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['32']))

		if jeedom_utils.testBit(int(data['msg5'],16),4) == 16:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['16']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['16']))

		if jeedom_utils.testBit(int(data['msg5'],16),3) == 8:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['8']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['8']))

		if jeedom_utils.testBit(int(data['msg5'],16),2) == 4:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['4']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['4']))

		if jeedom_utils.testBit(int(data['msg5'],16),1) == 2:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['2']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['2']))

		if jeedom_utils.testBit(int(data['msg5'],16),0) == 1:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['1']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['5']['1']))

		if jeedom_utils.testBit(int(data['msg6'],16),7) == 128:
			logging.debug("%-25s Enabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['6']['128']))
		else:
			logging.debug("%-25s Disabled" % str(RFXCOM_DECODE['packettype']['01']['msg']['6']['128']))
		globals.STATUS_PENDING = message
	# ---------------------------------------
	# 0x03 - Undecoded Message
	# ---------------------------------------
	if packettype == '03':
		action['id'] = str(id1 + id2)
		if RFXCOM_DECODE['packettype']['03'][subtype] == 'AE' :
			action['humidity'] = str(float(int(jeedom_utils.ByteToHex(message[9]),16) + int(jeedom_utils.ByteToHex(message[8]),16)) / 10)
			action['temperature'] = str(float(int(jeedom_utils.ByteToHex(message[10]),16) + int(jeedom_utils.ByteToHex(message[11]),16)) / 10)
		elif RFXCOM_DECODE['packettype']['03'][subtype] == 'Oregon 2' :
			action['temperature'] = str(jeedom_utils.ByteToHex(message[8]))
		else :
			indata = jeedom_utils.ByteToHex(message)
			for x in string.whitespace:
				indata = indata.replace(x,"")
			action['message'] = str( indata[4:])
	# ---------------------------------------
	# 0x10 Lighting1
	# ---------------------------------------
	if packettype == '10':
		try:
			action['housecode'] = RFXCOM_DECODE['packettype']['10']['housecode'][jeedom_utils.ByteToHex(message[4])]
		except Exception as e:
			action['housecode'] = "Error: Unknown housecode"
			logging.error("Unknown house command received, %s" % str(e))
			pass
		try:
			action['command'] = RFXCOM_DECODE['packettype']['10']['cmd'][jeedom_utils.ByteToHex(message[6])]
		except Exception as e:
			action['command'] = "Error: Unknown command"
			logging.error("Unknown command received, %s" % str(e))
			pass
		action['signal'] = str(decodeSignal(message[7]))
		action['unitcode'] = str(int(jeedom_utils.ByteToHex(message[5]), 16))
		action['id']=str(action['housecode'] + action['unitcode'])
	# ---------------------------------------
	# 0x11 Lighting2
	# ---------------------------------------
	if packettype == '11':
		action['id'] = str(jeedom_utils.ByteToHex(message[4]) + jeedom_utils.ByteToHex(message[5]) + jeedom_utils.ByteToHex(message[6]) + jeedom_utils.ByteToHex(message[7]))
		action['unitcode'] = str(int(jeedom_utils.ByteToHex(message[8]),16))
		action['command'] = str(RFXCOM_DECODE['packettype']['11']['cmd'][jeedom_utils.ByteToHex(message[9])])
		action['dimlevel'] = str(RFXCOM_DECODE['packettype']['11']['dimlevel'][jeedom_utils.ByteToHex(message[10])])
		action['signal'] = str(decodeSignal(message[11]))
	# ---------------------------------------
	# 0x12 Lighting3
	# ---------------------------------------
	if packettype == '12':
		if jeedom_utils.testBit(int(jeedom_utils.ByteToHex(message[5]),16),0) == 1:
			action['channel'] = '1'
		elif jeedom_utils.testBit(int(jeedom_utils.ByteToHex(message[5]),16),1) == 2:
			action['channel'] = '2'
		elif jeedom_utils.testBit(int(jeedom_utils.ByteToHex(message[5]),16),2) == 4:
			action['channel'] = '3'
		elif jeedom_utils.testBit(int(jeedom_utils.ByteToHex(message[5]),16),3) == 8:
			action['channel'] = '4'
		elif jeedom_utils.testBit(int(jeedom_utils.ByteToHex(message[5]),16),4) == 16:
			action['channel'] = '5'
		elif jeedom_utils.testBit(int(jeedom_utils.ByteToHex(message[5]),16),5) == 32:
			action['channel'] = '6'
		elif jeedom_utils.testBit(int(jeedom_utils.ByteToHex(message[5]),16),6) == 64:
			action['channel'] = '7'
		elif jeedom_utils.testBit(int(jeedom_utils.ByteToHex(message[5]),16),7) == 128:
			action['channel'] = '8'
		elif jeedom_utils.testBit(int(jeedom_utils.ByteToHex(message[6]),16),0) == 1:
			action['channel'] = '9'
		elif jeedom_utils.testBit(int(jeedom_utils.ByteToHex(message[6]),16),1) == 2:
			action['channel'] = '10'
		else:
			action['channel'] = '255'
		action['system'] = str(jeedom_utils.ByteToHex(message[4]))
		action['command'] = RFXCOM_DECODE['packettype']['12']['cmd'][jeedom_utils.ByteToHex(message[7])]
		action['signal'] = str(decodeSignal(message[8]))
		action['battery'] = str(decodeBattery(message[8]))
	# ---------------------------------------
	# 0x13 Lighting4
	# ---------------------------------------
	if packettype == '13':
		try:
			action['housecode'] = RFXCOM_DECODE['packettype']['13']['housecode'][jeedom_utils.ByteToHex(message[4])]
		except Exception as e:
			action['housecode'] = ""
			logging.error("Unknown house command received, " + str(e))
			pass
		try:
			action['command'] = RFXCOM_DECODE['packettype']['13']['cmd'][jeedom_utils.ByteToHex(message[6])]
		except Exception as e:
			action['command'] = ""
			logging.error("Unknown command received, " + str(e)+" => "+str(message))
			pass
		action['id'] = str(jeedom_utils.ByteToHex(message[4]) + jeedom_utils.ByteToHex(message[5]) + jeedom_utils.ByteToHex(message[6]) )
		action['unitcode'] = str(int(jeedom_utils.ByteToHex(message[5]), 16) + 1)
		action['pulse'] = str(((int(jeedom_utils.ByteToHex(message[7]),16) * 256) + int(jeedom_utils.ByteToHex(message[8]),16)))
		action['signal'] = str(decodeSignal(message[9])  )
	# ---------------------------------------
	# 0x14 Lighting5
	# ---------------------------------------
	if packettype == '14':
		action['unitcode'] = int(jeedom_utils.ByteToHex(message[7]),16)
		if subtype[1] in RFXCOM_DECODE['packettype']['14']['cmd']:
			action['command'] = RFXCOM_DECODE['packettype']['14']['cmd']['0'][jeedom_utils.ByteToHex(message[8])]
		else:
			action['command'] = "Unknown"
		if subtype == "00":
			action['level'] = str(jeedom_utils.ByteToHex(message[9]))
		if subtype <> '03' and subtype <> '05':
			action['unitcode'] = 0
		action['id'] = str(id1 + id2 + jeedom_utils.ByteToHex(message[6]))
		action['signal'] = str(decodeSignal(message[10]))
	# ---------------------------------------
	# 0x15 Lighting6
	# ---------------------------------------
	if packettype == '15':
		action['id'] = str(id1 + id2)
		action['groupcode'] = str(RFXCOM_DECODE['packettype']['15']['groupcode'][jeedom_utils.ByteToHex(message[6])])
		action['unitcode'] = str( int(jeedom_utils.ByteToHex(message[7]),16) )
		action['command'] = str(RFXCOM_DECODE['packettype']['15']['cmd'][jeedom_utils.ByteToHex(message[8])])
		action['signal'] = str(decodeSignal(message[11]))
	# ---------------------------------------
	# 0x16 Chime
	# ---------------------------------------
	if packettype == '16':
		action['id'] = str(id1 + id2)
		action['command'] = str(RFXCOM_DECODE['packettype']['16']['cmd'][jeedom_utils.ByteToHex(message[6])])
		action['signal'] = str(decodeSignal(message[7]))
	# ---------------------------------------
	# 0x19 Blinds1
	# ---------------------------------------
	if packettype == '19':
		action['id'] = str(id1 + id2 + jeedom_utils.ByteToHex(message[6]))
		action['unitcode'] = str(jeedom_utils.ByteToHex(message[8]))
		action['signal'] = str(decodeSignal(message[9]))
	# ---------------------------------------
	# 0x1A RTS
	# ---------------------------------------
	if packettype == '1A':
		if subtype == "00":
			unitcode = jeedom_utils.ByteToHex(message[6])
			if unitcode == "00":
				action['unitcode'] = "All"
			else:
				action['unitcode'] = str(unitcode)
		elif subtype == "01":
			action['unitcode'] = str(jeedom_utils.ByteToHex(message[6]))
		try:
			action['command'] = RFXCOM_DECODE['packettype']['1A']['cmd'][jeedom_utils.ByteToHex(message[8])]
		except Exception as e:
			action['command'] = "Error: Unknown command received"
			pass
		action['signal'] = decodeSignal(message[8])
		action['id'] = str(id1 + id2 + jeedom_utils.ByteToHex(message[6]))
	# ---------------------------------------
	# 0x20 Security1
	# ---------------------------------------
	if packettype == '20':
		action['id'] = str(id1 + id2 + jeedom_utils.ByteToHex(message[6]))
		action['status'] = str(RFXCOM_DECODE['packettype']['20']['status'][jeedom_utils.ByteToHex(message[7])])
		action['signal'] = str(decodeSignal(message[8]))
		action['battery'] = str(decodeBattery(message[8]))
	# ---------------------------------------
	# 0x30 Remote control and IR
	# ---------------------------------------
	if packettype == '30':
		if subtype in RFXCOM_DECODE['packettype']['30']:
			action['command'] = RFXCOM_DECODE['packettype']['30'][subtype][jeedom_utils.ByteToHex(message[5])]
		else:
			action['command'] = "Not implemented in RFXCOMD"
		if subtype == '00' or subtype == '02' or subtype == '03':
			action['signal'] = str(decodeSignal(message[6]))
		action['id'] = str(id1)
	# ---------------------------------------
	# 0x40 - Thermostat1
	# ---------------------------------------
	if packettype == '40':
		status_temp = str(jeedom_utils.testBit(int(jeedom_utils.ByteToHex(message[8]),16),0) + jeedom_utils.testBit(int(jeedom_utils.ByteToHex(message[8]),16),1))
		if jeedom_utils.testBit(int(jeedom_utils.ByteToHex(message[8]),16),7) == 128:
			action['mode'] = str(RFXCOM_DECODE['packettype']['40']['mode']['1'])
		else:
			action['mode'] = str(RFXCOM_DECODE['packettype']['40']['mode']['0'])
		action['id'] = str(id1 + id2)
		action['temperature'] = str(int(jeedom_utils.ByteToHex(message[6]), 16))
		action['temperatureset'] = str(int(jeedom_utils.ByteToHex(message[7]), 16))
		action['status'] = str(RFXCOM_DECODE['packettype']['40']['status'][status_temp])
		action['signal'] = str(decodeSignal(message[9]))
	# ---------------------------------------
	# 0x42 Thermostat3
	# ---------------------------------------
	if packettype == '42':
		if subtype == '00':
			action['unitcode'] = str(jeedom_utils.ByteToHex(message[4]))
		elif subtype == '01':
			action['unitcode'] = str(jeedom_utils.ByteToHex(message[4]) + jeedom_utils.ByteToHex(message[5]) + jeedom_utils.ByteToHex(message[6]))
		else:
			unitcode = "00"
		if subtype[1] in RFXCOM_DECODE['packettype']['42']['cmd']:
			action['command'] = str(RFXCOM_DECODE['packettype']['42']['cmd'][subtype[1]][jeedom_utils.ByteToHex(message[7])])
		else:
			action['command'] = '0'
		action['signal'] = str(decodeSignal(message[8]))
		action['id'] = str(id1 + id2)
	# ---------------------------------------
	# 0x50 - Temperature sensors
	# ---------------------------------------
	if packettype == '50':
		action['id'] = str(id1 + id2)
		action['temperature'] = str(decodeTemperature(message[6], message[7]))
		action['signal'] = str(decodeSignal(message[8]))
		action['battery'] = str(decodeBattery(message[8]))
	# ---------------------------------------
	# 0x51 - Humidity sensors
	# ---------------------------------------
	if packettype == '51':
		action['id'] = str(id1 + id2 )
		action['humidity'] = str(int(jeedom_utils.ByteToHex(message[6]),16))
		action['signal'] = str(decodeSignal(message[8]))
		action['battery'] = str(decodeBattery(message[8]))
	# ---------------------------------------
	# 0x52 - Temperature and humidity sensors
	# ---------------------------------------
	if packettype == '52':
		action['id'] = str(id1 + id2)
		action['temperature'] = str(decodeTemperature(message[6], message[7]))
		action['humidity'] = str(int(jeedom_utils.ByteToHex(message[8]),16))
		action['signal'] = str(decodeSignal(message[10]))
		action['battery'] = str(decodeBattery(message[10]))
	# ---------------------------------------
	# 0x54 - Temperature, humidity and barometric sensors
	# ---------------------------------------
	if packettype == '54':
		barometric_high = jeedom_utils.ByteToHex(message[10])
		barometric_low = jeedom_utils.ByteToHex(message[11])
		barometric_high = jeedom_utils.clearBit(int(barometric_high,16),7)
		barometric_high = barometric_high << 8
		action['id'] = str(id1 + id2)
		action['temperature'] = str(decodeTemperature(message[6], message[7]))
		action['humidity'] = str(int(jeedom_utils.ByteToHex(message[8]),16))
		action['barometric'] = str(( barometric_high + int(barometric_low,16)))
		action['signal'] = str(decodeSignal(message[13]))
		action['battery'] = str(decodeBattery(message[13]))
	# ---------------------------------------
	# 0x55 - Rain sensors
	# ---------------------------------------
	if packettype == '55':
		rainrate_high = jeedom_utils.ByteToHex(message[6])
		rainrate_low = jeedom_utils.ByteToHex(message[7])
		if subtype == '01':
			rainrate = int(rainrate_high,16) * 0x100 + int(rainrate_low,16)
		elif subtype == '02':
			rainrate = float( int(rainrate_high,16) * 0x100 + int(rainrate_low,16) ) / 100
		else:
			rainrate = 0
		raintotal1 = jeedom_utils.ByteToHex(message[8])
		raintotal2 = jeedom_utils.ByteToHex(message[9])
		raintotal3 = jeedom_utils.ByteToHex(message[10])
		if subtype <> '06':
			raintotal = float( (int(raintotal1, 16) * 0x1000) + (int(raintotal2, 16) * 0x100) + int(raintotal3, 16) ) / 10
		else:
			raintotal = 0
		action['id'] = str(id1 + id2)
		action['rainrate'] = str(rainrate)
		action['raintotal'] = str(raintotal)
		action['signal'] = str(decodeSignal(message[11]))
		action['battery'] = str(decodeBattery(message[11]))
	# ---------------------------------------
	# 0x56 - Wind sensors
	# ---------------------------------------
	if packettype == '56':
		if subtype <> "05":
			action['average'] = ( ( int(jeedom_utils.ByteToHex(message[8]),16) * 256 ) + int(jeedom_utils.ByteToHex(message[9]),16) ) * 0.1 * 3.6
		if subtype == "04":
			action['temperature'] = decodeTemperature(message[12], message[13])
			action['windchill'] = decodeTemperature(message[14], message[15])
		action['id'] = str(id1 + id2)
		action['windgust'] = str(( ( int(jeedom_utils.ByteToHex(message[10]),16) * 256 ) + int(jeedom_utils.ByteToHex(message[11]),16) ) * 0.1 * 3.6)
		action['direction'] = str(( ( int(jeedom_utils.ByteToHex(message[6]),16) * 256 ) + int(jeedom_utils.ByteToHex(message[7]),16) ))
		action['signal'] = str(decodeSignal(message[16]))
		action['battery'] = str(decodeBattery(message[16]))
	# ---------------------------------------
	# 0x57 UV Sensor
	# ---------------------------------------
	if packettype == '57':
		if subtype == '03':
			action['temperature'] = decodeTemperature(message[6], message[8])
		action['id'] = str(id1 + id2)
		action['uv'] = str(int(jeedom_utils.ByteToHex(message[6]), 16) * 10)
		action['signal'] = str(decodeSignal(message[9]))
		action['battery'] = str(decodeBattery(message[9]))
	# ---------------------------------------
	# 0x58 Date/Time sensor
	# ---------------------------------------
	if packettype == '58':
		date_yy = int(jeedom_utils.ByteToHex(message[6]), 16)
		date_mm = int(jeedom_utils.ByteToHex(message[7]), 16)
		date_dd = int(jeedom_utils.ByteToHex(message[8]), 16)
		date_string = "20%s-%s-%s" % (str(date_yy).zfill(2), str(date_mm).zfill(2), str(date_dd).zfill(2))
		time_hr = int(jeedom_utils.ByteToHex(message[10]), 16)
		time_min = int(jeedom_utils.ByteToHex(message[11]), 16)
		time_sec = int(jeedom_utils.ByteToHex(message[12]), 16)
		time_string = "%s:%s:%s" % (str(time_hr), str(time_min), str(time_sec))
		datetime_string = "%s %s" % (str(date_string), str(time_string))
		action['id'] = str(id1 + id2)
		action['date'] = str(date_string)
		action['time'] = str(time_string)
		action['dow'] = str(int(jeedom_utils.ByteToHex(message[9]), 16))
		action['signal'] = str(decodeSignal(message[13]))
		action['battery'] = str(decodeBattery(message[13]))
	# ---------------------------------------
	# 0x59 Current Sensor
	# ---------------------------------------
	if packettype == '59':
		action['id'] = str(id1 + id2)
		action['counter'] = str(int(jeedom_utils.ByteToHex(message[6]),16))
		action['channel1'] = str((int(jeedom_utils.ByteToHex(message[7]),16) * 0x100 + int(jeedom_utils.ByteToHex(message[8]),16)) * 0.1)
		action['channel2'] = str((int(jeedom_utils.ByteToHex(message[9]),16) * 0x100 + int(jeedom_utils.ByteToHex(message[10]),16)) * 0.1)
		action['channel3'] = str((int(jeedom_utils.ByteToHex(message[11]),16) * 0x100 + int(jeedom_utils.ByteToHex(message[12]),16)) * 0.1)
		action['total'] = str(float ((int(jeedom_utils.ByteToHex(message[7]), 16) * 0x10000000000 + int(jeedom_utils.ByteToHex(message[8]), 16) * 0x100000000 +int(jeedom_utils.ByteToHex(message[9]), 16) * 0x1000000 + int(jeedom_utils.ByteToHex(message[10]), 16) * 0x10000 + int(jeedom_utils.ByteToHex(message[11]), 16) * 0x100 + int(jeedom_utils.ByteToHex(message[12]), 16) ) / 230))
		action['signal'] = str(decodeSignal(message[13]))
		action['battery'] = str(decodeBattery(message[13]))
	# ---------------------------------------
	# 0x5A Energy sensor
	# ---------------------------------------
	if packettype == '5A':
		usage = int ((int(jeedom_utils.ByteToHex(message[11]), 16) * 0x10000000000 + int(jeedom_utils.ByteToHex(message[12]), 16) * 0x100000000 +int(jeedom_utils.ByteToHex(message[13]), 16) * 0x1000000 + int(jeedom_utils.ByteToHex(message[14]), 16) * 0x10000 + int(jeedom_utils.ByteToHex(message[15]), 16) * 0x100 + int(jeedom_utils.ByteToHex(message[16]), 16) ) / 230)
		action['id'] = str(id1 + id2)
		action['count'] = str(int(jeedom_utils.ByteToHex(message[6]), 16))
		action['instant'] = str(int(jeedom_utils.ByteToHex(message[7]), 16) * 0x1000000 + int(jeedom_utils.ByteToHex(message[8]), 16) * 0x10000 + int(jeedom_utils.ByteToHex(message[9]), 16) * 0x100  + int(jeedom_utils.ByteToHex(message[10]), 16))
		if usage <> 0:
			action['total'] = str(usage)
		action['signal'] = str(decodeSignal(message[17]))
		action['battery'] = str(decodeBattery(message[17]))	
	# ---------------------------------------
	# 0x5B Current Sensor
	# ---------------------------------------
	if packettype == '5B':
		total = float ((int(jeedom_utils.ByteToHex(message[13]), 16) * 0x10000000000 + int(jeedom_utils.ByteToHex(message[14]), 16) * 0x100000000 +int(jeedom_utils.ByteToHex(message[15]), 16) * 0x1000000 + int(jeedom_utils.ByteToHex(message[16]), 16) * 0x10000 + int(jeedom_utils.ByteToHex(message[17]), 16) * 0x100 + int(jeedom_utils.ByteToHex(message[18]), 16) ) / 230)
		totalW = int((int(jeedom_utils.ByteToHex(message[7]),16) * 0x100 + int(jeedom_utils.ByteToHex(message[8]),16) + int(jeedom_utils.ByteToHex(message[9]),16) * 0x100 + int(jeedom_utils.ByteToHex(message[10]),16) + int(jeedom_utils.ByteToHex(message[11]),16) * 0x100 + int(jeedom_utils.ByteToHex(message[12]),16)) * 230) * 0.1
		action['id'] = str(id1 + id2)
		action['counter'] = str(int(jeedom_utils.ByteToHex(message[6]),16))
		action['channel1'] = str((int(jeedom_utils.ByteToHex(message[7]),16) * 0x100 + int(jeedom_utils.ByteToHex(message[8]),16)) * 0.1)
		action['channel2'] = str((int(jeedom_utils.ByteToHex(message[9]),16) * 0x100 + int(jeedom_utils.ByteToHex(message[10]),16)) * 0.1)
		action['channel3'] = str((int(jeedom_utils.ByteToHex(message[11]),16) * 0x100 + int(jeedom_utils.ByteToHex(message[12]),16)) * 0.1)
		action['power1'] = str(int((int(jeedom_utils.ByteToHex(message[7]),16) * 0x100 + int(jeedom_utils.ByteToHex(message[8]),16)) * 230) * 0.1)
		action['power2'] = str(int((int(jeedom_utils.ByteToHex(message[9]),16) * 0x100 + int(jeedom_utils.ByteToHex(message[10]),16)) * 230) * 0.1)
		action['power3'] = str(int((int(jeedom_utils.ByteToHex(message[11]),16) * 0x100 + int(jeedom_utils.ByteToHex(message[12]),16)) * 230) * 0.1)
		if total <> 0:
			action['total'] = str(total)
		if totalW <> 0:
			action['totalW'] = str(totalW)
		action['signal'] = str(decodeSignal(message[19]))
		action['battery'] = str( decodeBattery(message[19]))
	# ---------------------------------------
	# 0x5C Power Sensors
	# ---------------------------------------
	if packettype == '5C':
		action['id'] = str(id1 + id2)
		action['voltage'] = str(int(jeedom_utils.ByteToHex(message[6]),16))
		action['current'] = str((int(jeedom_utils.ByteToHex(message[7]),16) * 0x100 + int(jeedom_utils.ByteToHex(message[8]),16)) * 0.01)
		action['powerfactor'] = str((int(jeedom_utils.ByteToHex(message[13]), 16)) * 0.01)
		action['signal'] = str(decodeSignal(message[15]))
	# ---------------------------------------
	# 0x70 RFXsensor
	# ---------------------------------------
	if packettype == '70':
		if subtype == '00':
			action['temperature'] = float(decodeTemperature(message[5], message[6])) * 0.1
		if subtype == '01' or subtype == '02':
			action['voltage'] = (int(jeedom_utils.ByteToHex(message[5]), 16) * 256) + int(jeedom_utils.ByteToHex(message[6]), 16)
		action['id'] = str(id1)
		action['signal'] = str(signal)
	# ---------------------------------------
	# 0x71 RFXmeter
	# ---------------------------------------
	if packettype == '71':
		try:
			action['power'] = decodePower(message[7], message[8], message[9])
		except Exception, e:
			logging.error('Exception: '+str(e))
		action['id'] = str(id1 + id2)

	logging.debug('Decode data : '+str(action))
	try:
		if len(action) <= 3:
			return
		if action['id'] not in globals.KNOWN_DEVICES:
			if not globals.INCLUDE_MODE:
				return
			globals.JEEDOM_COM.send_change_immediate({'include_mode' : 0});
			globals.INCLUDE_MODE = False
		key = 'devices::'+action['id']+str(packettype);
		if 'unitcode' in action:
			key += str(action['unitcode']);
		globals.JEEDOM_COM.add_changes(key,action)
	except Exception, e:
		pass
	return

# ----------------------------------------------------------------------------

def read_socket(name):
	while 1:
		time.sleep(0.02)
		try:
			global JEEDOM_SOCKET_MESSAGE
			if not JEEDOM_SOCKET_MESSAGE.empty():
				logging.debug("Message received in socket JEEDOM_SOCKET_MESSAGE")
				message = json.loads(jeedom_utils.stripped(JEEDOM_SOCKET_MESSAGE.get()))
				if message['apikey'] != _apikey:
					logging.error("Invalid apikey from socket : " + str(message))
					return
				if message['apikey'] != _apikey:
						logging.error("Invalid apikey from socket : " + str(message))
						return
				if message['cmd'] == 'add':
					logging.debug('Add device : '+str(message['device']))
					if 'id' in message['device'] :
						globals.KNOWN_DEVICES[message['device']['id']] = message['device']['id']
				elif message['cmd'] == 'remove':
					logging.debug('Remove device : '+str(message['device']))
					if 'id' in message['device'] and message['device']['id'] in globals.KNOWN_DEVICES :
						del globals.KNOWN_DEVICES[message['device']['id']]
				elif message['cmd'] == 'include_mode':
					if int(message['state']) == 1:
						logging.debug('Enter in include mode')
						globals.INCLUDE_MODE = True
					else :
						logging.debug('Leave in include mode')
						globals.INCLUDE_MODE = False
					globals.JEEDOM_COM.send_change_immediate({'include_mode' : message['state']});
				elif message['cmd'] == 'send':
					if isinstance(message['data'], list):
						for data in message['data']:
							try:
								send_rfxcom(data)
							except Exception, e:
								logging.error('Send command to rfxcom error : '+str(e))
					else:
						try:
							send_rfxcom(message['data'])
						except Exception, e:
							logging.error('Send command to rfxcom error : '+str(e))
		except Exception,e:
			logging.error('Error on read socket : '+str(e))

# ----------------------------------------------------------------------------	

def send_rfxcom(message):
	if test_rfxcom(message):
		globals.JEEDOM_SERIAL.flushOutput()
		globals.JEEDOM_SERIAL.flushInput()
		logging.debug("Write message to serial port")
		globals.JEEDOM_SERIAL.write(message.decode('hex') )
		logging.debug("Write message ok : "+ jeedom_utils.ByteToHex(message.decode('hex')))
		try:
			logging.debug("Decode message")
			decodePacket(message.decode('hex'))
		except Exception, e:
			logging.error('Unrecognizable packet : '+str(e))
	else:
		logging.error("Invalid message from socket.")

# ----------------------------------------------------------------------------

def test_rfxcom( message ):
	logging.debug("Test message: " + message)
	message = jeedom_utils.stripped(message)
	try:
		message = message.replace(' ', '')
	except Exception, e:
		logging.debug("Error: Removing white spaces")
		return False
	try:
		int(message,16)
	except Exception, e:
		logging.debug("Error: Packet not hex format")
		return False
	if len(message) % 2:
		logging.debug("Error: Packet length not even")
		return False
	if jeedom_utils.ByteToHex(message.decode('hex')[0]) == "00":
		logging.debug("Error: Packet first byte is 00")
		return False
	if not len(message.decode('hex')) > 1:
		logging.debug("Error: Packet is not longer than one byte")
		return False
	cmd_len = int( jeedom_utils.ByteToHex( message.decode('hex')[0]),16 )
	if not len(message.decode('hex')) == (cmd_len + 1):
		logging.debug("Error: Packet length is not valid")
		return False
	return True

# ----------------------------------------------------------------------------

def read_rfxcom(name):
	while 1:
		time.sleep(0.02)
		message = None
		try:
			byte = globals.JEEDOM_SERIAL.read()
		except Exception, e:
			logging.error("Error in read_rfxcom: " + str(e))
			if str(e) == '[Errno 5] Input/output error':
				logging.error("Exit 1 because this exeption is fatal")
				shutdown()
		try:
			if byte:
				message = byte + globals.JEEDOM_SERIAL.readbytes(ord(byte))
				logging.debug("Message: " + str(jeedom_utils.ByteToHex(message)))
				if jeedom_utils.ByteToHex(message[0]) <> "00":
					if (len(message) - 1) == ord(message[0]):
						try:
							decodePacket(message)
						except Exception, e:
							logging.error("Error: unrecognizable packet (" + jeedom_utils.ByteToHex(message) + ")"+' : '+str(e))
						#rawcmd = jeedom_utils.ByteToHex(message).replace(' ', '')
						#return rawcmd
					else:
						logging.error("Error: Incoming packet not valid length (" + jeedom_utils.ByteToHex(message) + ")." )
		except OSError, e:
			logging.error("Error in read_rfxcom on decode message : " + str(jeedom_utils.ByteToHex(message))+" => "+str(e))

# ----------------------------------------------------------------------------

def setprotocol(protocol,nosend = 0):
	msg = []
	msg3 = []
	msg4 = []
	msg5 = []
	msg6 = []
	for i in range(33): 
		if str(i) in protocol:
			logging.debug("Enable protocol "+str(i))
			msg.insert(i, 1)
		else:
			logging.debug("Disable protocol "+str(i))
			msg.insert(i, 0)
	msg3 = msg[0:8]
	msg4 = msg[8:16]
	msg5 = msg[16:24]
	msg6 = msg[24:32]
	try:
		msg3_bin = str(msg[0]) + str(msg[1]) + str(msg[2]) + str(msg[3]) + str(msg[4]) + str(msg[5]) + str(msg[6]) + str(msg[7])
		msg3_int = int(msg3_bin,2)
		msg3_hex = hex(msg3_int)[2:].zfill(2)
		msg4_bin = str(msg[8]) + str(msg[9]) + str(msg[10]) + str(msg[11]) + str(msg[12]) + str(msg[13]) + str(msg[14]) + str(msg[15])
		msg4_int = int(msg4_bin,2)
		msg4_hex = hex(msg4_int)[2:].zfill(2)
		msg5_bin = str(msg[16]) + str(msg[17]) + str(msg[18]) + str(msg[19]) + str(msg[20]) + str(msg[21]) + str(msg[22]) + str(msg[23])
		msg5_int = int(msg5_bin,2)
		msg5_hex = hex(msg5_int)[2:].zfill(2)
		msg6_bin = str(msg[24]) + str(msg[25]) + str(msg[26]) + str(msg[27]) + str(msg[28]) + str(msg[29]) + str(msg[30]) + str(msg[31])
		msg6_int = int(msg6_bin,2)
		msg6_hex = hex(msg6_int)[2:].zfill(2)
	except Exception as e:
		logging.error("Error: %s" % str(e))
		sys.exit(1)
	logging.debug("msg3: %s / %s" % (str(msg3), msg3_hex))
	logging.debug("msg4: %s / %s" % (str(msg4), msg4_hex))
	logging.debug("msg5: %s / %s" % (str(msg5), msg5_hex))
	logging.debug("msg6: %s / %s" % (str(msg6), msg6_hex))
	command = "0d000002035300%s%s%s%s000000" % (msg3_hex, msg4_hex, msg5_hex, msg6_hex)
	logging.debug("Command: %s" % command)
	if nosend == 0:
		globals.JEEDOM_SERIAL.write(command.decode('hex'))
	else:
		return command

# ----------------------------------------------------------------------------

def listen():
	logging.debug("Start listening...")
	jeedom_socket.open()
	globals.JEEDOM_SERIAL.open()
	globals.JEEDOM_SERIAL.flushOutput()
	globals.JEEDOM_SERIAL.flushInput()
	try:
		thread.start_new_thread( read_socket, ('socket',))
		logging.debug('Read Socket Thread Launched')
		thread.start_new_thread( read_rfxcom, ('read',))
		logging.debug('Read Device Thread Launched')
	except KeyboardInterrupt:
		logging.error("KeyboardInterrupt, shutdown")
		shutdown()
	time.sleep(1)
	logging.debug("Send rfxcomd_reset")
	globals.JEEDOM_SERIAL.write("0d00000000000000000000000000".decode('hex'))
	logging.debug("Sleep 1 sec")
	time.sleep(1)
	globals.JEEDOM_SERIAL.flushInput()
	logging.debug("Send get status test")
	globals.JEEDOM_SERIAL.write("0d00000102000000000000000000".decode('hex'))
	globals.STATUS_PENDING = 1
	while globals.STATUS_PENDING == 1:
		if (globals.PENDING_TIME + 29) > int(time.time()) :
			time.sleep(1)
		else :
			logging.error("Timeout waiting status ")
			shutdown()
	logging.debug("Firmware is " + str(globals.DEVICE_FIRMWARE))
	logging.debug("Type is " + str(globals.DEVICE_TYPE))
	if globals.DEVICE_FIRMWARE < 248 and globals.DEVICE_TYPE != 16:
		try:
			setprotocol(string.split(_protocol, ','))
			time.sleep(1)
			logging.debug("All is ok, sending start")
			globals.JEEDOM_SERIAL.write("0d00000207000000000000000000".decode('hex'))
			time.sleep(1)
			logging.debug("All is ok, Ready for operation")
		except Exception as e:
			logging.error("Could not create protocol message")
			pass
	else:
		try:
			wantedprotocol = setprotocol(string.split(_protocol, ','),1)
		except Exception as e:
			logging.error("Could not create protocol message")
			pass
		logging.debug("Protocol actually activated " + str(jeedom_utils.ByteToHex(globals.STATUS_PENDING)).replace(' ','')[14:22].lower())
		logging.debug("Actual Frequency " + str(jeedom_utils.ByteToHex(globals.STATUS_PENDING)).replace(' ','')[10:12])
		logging.debug("Wanted protocol " + wantedprotocol[14:22].lower())
		if (str(jeedom_utils.ByteToHex(globals.STATUS_PENDING)).replace(' ','')[14:22].lower() != wantedprotocol[14:22].lower()) or str(jeedom_utils.ByteToHex(globals.STATUS_PENDING)).replace(' ','')[10:12] != '53':
			logging.error("Setting protocols as we need to change it")
			try:
				setprotocol(string.split(_protocol, ','))
			except Exception as e:
				logging.error("Could not create protocol message")
				pass
			globals.STATUS_PENDING = 2
			while globals.STATUS_PENDING == 2:
				if (globals.PENDING_TIME + 29) > int(time.time()) :
					time.sleep(1)
				else :
					logging.error("Timeout waiting status")
					shutdown()
			logging.debug("Protocol actually activated " + str(jeedom_utils.ByteToHex(globals.STATUS_PENDING)).replace(' ','')[14:22].lower())
			logging.debug("Actual Frequency " + str(jeedom_utils.ByteToHex(globals.STATUS_PENDING)).replace(' ','')[10:12])
			logging.debug("Wanted protocol " + wantedprotocol[14:22].lower())
			if (str(jeedom_utils.ByteToHex(globals.STATUS_PENDING)).replace(' ','')[14:22].lower() != wantedprotocol[14:22].lower()) or str(jeedom_utils.ByteToHex(globals.STATUS_PENDING)).replace(' ','')[10:12] != '53':
				logging.error("Issue setting protocol or frequency maybe incompatible protocols choosen or too old firmware, shutdown")
				shutdown()
		logging.debug("All is ok, sending start")
		globals.JEEDOM_SERIAL.write("0d00000207000000000000000000".decode('hex'))
		globals.STATUS_PENDING = 3
		while globals.STATUS_PENDING == 3:
			if (globals.PENDING_TIME + 29) > int(time.time()) :
				time.sleep(1)
			else :
				logging.error("Timeout waiting status")
				shutdown()
		if globals.STATUS_PENDING == 'Copyright RFXCOM':
			logging.debug("All is ok, Ready for operation")
		else:
			logging.error("RFXCom did not respond OK, look at debug !")
			shutdown()
# ----------------------------------------------------------------------------

def handler(signum=None, frame=None):
	logging.debug("Signal %i caught, exiting..." % int(signum))
	shutdown()

def shutdown():
	logging.debug("Shutdown")
	logging.debug("Removing PID file " + str(_pidfile))
	try:
		os.remove(_pidfile)
	except:
		pass
	try:
		jeedom_socket.close()
	except:
		pass
	try:
		globals.JEEDOM_SERIAL.close()
	except:
		pass
	logging.debug("Exit 0")
	sys.stdout.flush()
	os._exit(0)

# ----------------------------------------------------------------------------

_log_level = "error"
_socket_port = 55000
_socket_host = '127.0.0.1'
_device = 'auto'
_pidfile = '/tmp/rfxcomd.pid'
_apikey = ''
_callback = ''
_serial_rate = 38400
_serial_timeout = 9
_cycle = 0.3
_protocol = None

parser = argparse.ArgumentParser(description='Rfxcom Daemon for Jeedom plugin')
parser.add_argument("--device", help="Device", type=str)
parser.add_argument("--socketport", help="Socketport for server", type=str)
parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
parser.add_argument("--callback", help="Callback", type=str)
parser.add_argument("--apikey", help="Apikey", type=str)
parser.add_argument("--cycle", help="Cycle to send event", type=str)
parser.add_argument("--protocol", help="Protocol to enable", type=str)
parser.add_argument("--serialrate", help="Device serial rate", type=str)
parser.add_argument("--pid", help="Pid file", type=str)
args = parser.parse_args()

if args.device:
	_device = args.device
if args.socketport:
	_socket_port = int(args.socketport)
if args.loglevel:
	_log_level = args.loglevel
if args.callback:
	_callback = args.callback
if args.apikey:
	_apikey = args.apikey
if args.pid:
	_pidfile = args.pid
if args.protocol:
	_protocol = args.protocol
if args.serialrate:
	_serial_rate = int(args.serialrate)
if args.cycle:
	_cycle = float(args.cycle)

jeedom_utils.set_log_level(_log_level)

logging.info('Start rfxcomd')
logging.info('Log level : '+str(_log_level))
logging.info('Socket port : '+str(_socket_port))
logging.info('Socket host : '+str(_socket_host))
logging.info('PID file : '+str(_pidfile))
logging.info('Device : '+str(_device))
logging.info('Apikey : '+str(_apikey))
logging.info('Callback : '+str(_callback))
logging.info('Cycle : '+str(_cycle))
logging.info('Serial rate : '+str(_serial_rate))
logging.info('Serial timeout : '+str(_serial_timeout))
logging.info('Protocol : '+str(_protocol))

if _device == 'auto':
	_device = jeedom_utils.find_tty_usb('0403','6001','rfx')
	logging.info('Find device : '+str(_device))

if _device is None:
	logging.error('No device found')
	shutdown()	

signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)	

try:
	jeedom_utils.write_pid(str(_pidfile))
	globals.JEEDOM_COM = jeedom_com(apikey = _apikey,url = _callback,cycle=_cycle)
	if not globals.JEEDOM_COM.test():
		logging.error('Network communication issues. Please fixe your Jeedom network configuration.')
		shutdown()
	globals.JEEDOM_SERIAL = jeedom_serial(device=_device,rate=_serial_rate,timeout=_serial_timeout)
	jeedom_socket = jeedom_socket(port=_socket_port,address=_socket_host)
	listen()
except Exception, e:
	logging.error('Fatal error : '+str(e))
	logging.debug(traceback.format_exc())
	shutdown()