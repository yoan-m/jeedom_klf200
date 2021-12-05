const api = require("klf-200-api");
const fs = require("fs");
const express = require('express');
const app = express();

app.listen(9123, () => {
    console.log("Start server");
})

const myFingerprint = fs.readFileSync("/var/www/html/plugins/klf200/resources/fingerprint");
const myCA = fs.readFileSync("/var/www/html/plugins/klf200/resources/velux-cert.pem");
const conn = new api.Connection(process.argv[2], myCA, myFingerprint.toString().trim());
let myProducts;
async function login() {

    console.log("Connexion");
  	console.log("Fingerpirnt " + myFingerprint);
  try{
    await conn.loginAsync(process.argv[3]);
    }catch(e){
     	console.error("Login error");
      login();
                 return;
    }
    console.log("Fetching products");
    myProducts = await api.Products.createProductsAsync(conn);
    console.log("Ready");
}

login();

function getDevice(nodeId) {
    for (const product of myProducts.Products) {
        if (product.NodeID == nodeId) {
            return product;
        }
    }
}
app.get('/set/:device/:position', async (req, res) => {
    if (isNaN(req.params.device) || isNaN(req.params.position)) {
        res.send({ 'result': 'fail', 'reason': 'node or position not provided' });
    }
    try {
        const product = getDevice(req.params.device);
        if (product) {
            await product.setTargetPositionAsync(req.params.position/100);
        } else {
            res.send({ 'result': 'fail', 'reason': 'device unknown' });
        }
    }
    catch (e) {
        res.send({ 'result': 'fail', 'reason': 'exception during execution', 'message': e });
    }

    res.send({ 'result': 'ok', 'device': req.params.device, 'position': req.params.position });

});

app.get('/set/orientation/:device/:position', async (req, res) => {
    if (isNaN(req.params.device) || isNaN(req.params.position)) {
        res.send({ 'result': 'fail', 'reason': 'node or position not provided' });
    }
    try {
        const product = getDevice(req.params.device);
        if (product) {
            await product.setTargetPositionAsync(req.params.position);
        } else {
            res.send({ 'result': 'fail', 'reason': 'device unknown' });
        }
    }
    catch (e) {
        res.send({ 'result': 'fail', 'reason': 'exception during execution', 'message': e });
    }

    res.send({ 'result': 'ok', 'device': req.params.device, 'position': req.params.position });

});

app.get('/stop/:device', async (req, res) => {
    if (isNaN(req.params.device)) {
        res.send({ 'result': 'fail', 'reason': 'node not provided' });
    }
    try {

        const product = getDevice(req.params.device);
        if (product) {
            await product.stopAsync(req.params.position);
        } else {
            data = { 'result': 'fail', 'reason': 'device unknown' };
        }
    }
    catch (e) {
        res.send({ 'result': 'fail', 'reason': 'exception during execution', 'message': e });
    }
    res.send({ 'result': 'ok', 'device': req.params.device });
});
const NO_TYPE = 0
const VENETIAN_BLIND = 1
const ROLLER_SHUTTER = 2
const AWNING = 3
const WINDOW_OPENER = 4
const GARAGE_OPENER = 5
const LIGHT = 6
const GATE_OPENER = 7
const ROLLING_DOOR_OPENER = 8
const LOCK = 9
const BLIND = 10
const SECURE_CONFIGURATION_DEVICE = 11
const BEACON = 12
const DUAL_SHUTTER = 13
const HEATING_TEMPERATURE_INTERFACE = 14
const ON_OFF_SWITCH = 15
const HORIZONTAL_AWNING = 16
const EXTERNAL_VENETIAN_BLIND = 17
const LOUVRE_BLINT = 18
const CURTAIN_TRACK = 19
const VENTILATION_POINT = 20
const EXTERIOR_HEATING = 21
const HEAT_PUMP = 22
const INTRUSION_ALARM = 23
const SWINGING_SHUTTER = 24
function getType(type) {
    switch (type) {
        case WINDOW_OPENER:
            return 'Window';
        case ROLLER_SHUTTER:
            return 'RollerShutter';
        case AWNING:
            return 'Awning';
        case BLIND:
            return 'Blind';
        case GARAGE_OPENER:
            return 'GarageDoor';
        case LIGHT:
            return 'Light';
    }
}
app.get('/devices', async (req, res) => {
    let data = { 'result': 'ok', 'devices': [] };
    try {
        myProducts = await api.Products.createProductsAsync(conn);
        for (const product of myProducts.Products) {
            data.devices.push(product);
            switch (product._TypeID) {
                case ROLLER_SHUTTER, WINDOW_OPENER, AWNING, BLIND, GARAGE_OPENER:
                    if (product.CurrentPositionRaw) {
                        data.devices.push({ 'id': product.NodeID, 'name': product.Name, 'type': getType(product._TypeID) });
                    } else {

                        data.devices.push({ 'id': product.NodeID, 'name': product.Name, 'type': getType(product._TypeID), 'position': product.CurrentPositionRaw });
                    }
                    break;
                case LIGHT:
                    data.devices.push({ 'id': product.NodeID, 'name': product.Name, 'type': getType(product._TypeID) });
                    break;
            }
        }
    } catch (e) {
        data = { 'result': 'fail' };
    }
    res.send(data);
})