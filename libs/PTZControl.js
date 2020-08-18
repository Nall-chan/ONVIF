
function sendPTZstart(event) {

    this.childNodes[1].setAttribute("stroke", "#000000");
    this.childNodes[1].setAttribute("fill", "#5B9BA2");
    this.childNodes[3].setAttribute("fill", "#000000");
    var action = this.id.split('_');
    var Key = window["Key"+ action[0]];
    myPTZRequestActionGet({ url: "hook/ONVIF/PTZ/" + action[0] + "?action=StartPTZ&value=" + action[1] + "&authorization=" + Key});
}
function sendPTZstop(event) {
    this.childNodes[1].setAttribute("stroke", "none");
    this.childNodes[1].setAttribute("fill", "#33666C");
    this.childNodes[3].setAttribute("fill", "#ffffff");
    var action = this.id.split('_');
    var Key = window["Key"+ action[0]];
    myPTZRequestActionGet({ url: "hook/ONVIF/PTZ/" + action[0] + "?action=StopPTZ&value=" + action[1] + "&authorization=" + Key});
}
function initPTZ(instanceId) {
    document.getElementById(instanceId + "_left").addEventListener("mousedown", sendPTZstart);
    document.getElementById(instanceId + "_right").addEventListener("mousedown", sendPTZstart);
    document.getElementById(instanceId + "_up").addEventListener("mousedown", sendPTZstart);
    document.getElementById(instanceId + "_down").addEventListener("mousedown", sendPTZstart);
    document.getElementById(instanceId + "_left").addEventListener("mouseup", sendPTZstop);
    document.getElementById(instanceId + "_right").addEventListener("mouseup", sendPTZstop);
    document.getElementById(instanceId + "_up").addEventListener("mouseup", sendPTZstop);
    document.getElementById(instanceId + "_down").addEventListener("mouseup", sendPTZstop);
    document.getElementById(instanceId + "_near").addEventListener("mousedown", sendPTZstart);
    document.getElementById(instanceId + "_far").addEventListener("mousedown", sendPTZstart);
    document.getElementById(instanceId + "_near").addEventListener("mouseup", sendPTZstop);
    document.getElementById(instanceId + "_far").addEventListener("mouseup", sendPTZstop);

}

function myPTZRequestActionGet(o) {
    var oReq = new XMLHttpRequest();
    oReq.addEventListener('loadend', myPTZRequestActionGetLoadEnd);
    oReq.open('GET', o.url, true);
    oReq.send();
}

function myPTZRequestActionGetLoadEnd() {
    if (this.status >= 200 && this.status < 300) {
        if (this.responseText !== "OK") {
            sendError(this.responseText);
        }
    } else {
        sendError(this.statusText);
    }
}

function sendError(data) {
    var notify = document.getElementsByClassName("ipsNotifications")[0];
    var newDiv = document.createElement("div");
    newDiv.innerHTML = '<div style="height:auto; visibility: hidden; overflow: hidden; transition: height 500ms ease-in 0s" class="ipsNotification"><div class="spacer"></div><div class="message icon error" onclick="document.getElementsByClassName(\'ipsNotifications\')[0].removeChild(this.parentNode);"><div class="ipsIconClose"></div><div class="content"><div class="title">Fehler</div><div class="text">' + data + '</div></div></div></div>';
    if (notify.childElementCount === 0)
        var thisDiv = notify.appendChild(newDiv.firstChild);
    else
        var thisDiv = notify.insertBefore(newDiv.firstChild, notify.childNodes[0]);
    var newheight = window.getComputedStyle(thisDiv, null)["height"];
    thisDiv.style.height = "0px";
    thisDiv.style.visibility = "visible";
    function sleep(time) {
        return new Promise((resolve) => setTimeout(resolve, time));
    }
    sleep(10).then(() => {
        thisDiv.style.height = newheight;
    });
}
var Key%%InstanceId%% = "%%Authorization%%";
initPTZ(%%InstanceId%%);