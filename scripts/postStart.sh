#!/bin/bash
###Clear the PL_TRANSITION Flag on startup in case it was left over from before
##custom email subject
#PL_TRANSITIONED=$(grep -i "^PL_TRANSITIONED\s*=.*" /home/fpp/media/config/plugin.FPP-VotingAPI-Integration | sed -e "s/.*=\s*//" -e 's/"//g')
##PL_TRANSITIONED=$(awk -f ${FPPDIR}/scripts/readSetting.awk /home/fpp/media/config/plugin.FPP-VotingAPI-Integration setting=PL_TRANSITIONED)
#
##if Playlist has transitioned, set the value to false.
##this value might be left over from the previous show, and we want the plugin to react on the first run and not skip
##thinking it has already changed playlists
#if [ "${PL_TRANSITIONED}"  == "true" ] || [ ${PL_TRANSITIONED} -eq 1 ]
#then
#    sed -i "/^PL_TRANSITIONED =/s/=.*/= \"0\"/" /home/fpp/media/config/plugin.FPP-VotingAPI-Integration
#fi