PROGRESS_FILE=/tmp/dependancy_klf200_in_progress
if [ ! -z $1 ]; then
	PROGRESS_FILE=$1
fi
IP_ADDR=NULL
if [ ! -z $2 ]; then
	IP_ADDR=$2
fi
touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*             Installation des dépendances             *"
echo "********************************************************"

sudo apt-get update
sudo apt-get -y install openssl
echo 40 > ${PROGRESS_FILE}
if [ -x /usr/bin/nodejs ]; then
	actual=`nodejs -v | awk -F v '{ print $2 }' | awk -F . '{ print $1 }'`;
	echo "Version actuelle : ${actual}"
else
	actual=0;
	echo "Nodejs non installé"
fi
if [ $actual -ge 8 ]
then
	echo "Ok, version suffisante";
else
	echo "KO, version obsolète à upgrader";
	echo "Suppression du Nodejs existant et installation du paquet recommandé"
	sudo apt-get -y --purge autoremove nodejs npm
	arch=`arch`;
	echo 30 > $LOG
	if [ $arch == "armv6l" ]
	then
		echo "Raspberry 1 détecté, utilisation du paquet pour armv6"
		sudo rm /etc/apt/sources.list.d/nodesource.list
		wget http://node-arm.herokuapp.com/node_latest_armhf.deb
		sudo dpkg -i node_latest_armhf.deb
		sudo ln -s /usr/local/bin/node /usr/local/bin/nodejs
		rm node_latest_armhf.deb
	else
		echo "Utilisation du dépot officiel"
		curl -sL https://deb.nodesource.com/setup_8.x | sudo -E bash -
		sudo apt-get install -y nodejs
	fi
	new=`nodejs -v`;
	echo "Version actuelle : ${new}"
fi
echo 45 > ${PROGRESS_FILE}

BASEDIR=$(dirname "$0")
echo ${BASEDIR}
cd ${BASEDIR}
sudo npm install klf-200-api
echo 70 > ${PROGRESS_FILE}
sudo npm install express
echo 90 > ${PROGRESS_FILE}
if [ ! -z IP_ADDR ]; then
	sudo echo -n | openssl s_client -connect ${IP_ADDR}:51200 | sed -ne '/-BEGIN CERTIFICATE-/,/-END CERTIFICATE-/p' > velux-cert.pem
    echo -n | openssl x509 -noout -fingerprint -sha1 -inform pem -in velux-cert.pem | grep -Po '(([A-Z0-9]{2}:)+[A-Z0-9]{2})' > ${BASEDIR}/fingerprint
else
	echo "Aucune adresse IP"
fi
echo 100 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*             Installation terminée                    *"
echo "********************************************************"
rm ${PROGRESS_FILE}