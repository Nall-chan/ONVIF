function sendPTZstart(event) {
    event.currentTarget.childNodes[0].setAttribute("stroke-width", "10");
    event.currentTarget.childNodes[1].setAttribute("stroke-width", "10");
    event.currentTarget.childNodes[2].setAttribute("opacity", "0.6");
    var action = this.id.split('_');
    myPTZRequestActionGet({url: "hook/ONVIF/PTZ/" + action[0] + "?action=StartPTZ&value=" + action[1]});
}
function sendPTZstop(event) {

    event.currentTarget.childNodes[0].setAttribute("stroke-width", "2");
    event.currentTarget.childNodes[1].setAttribute("stroke-width", "2");
    event.currentTarget.childNodes[2].setAttribute("opacity", "0.2");
    var action = this.id.split('_');
    myPTZRequestActionGet({url: "hook/ONVIF/PTZ/" + action[0] + "?action=StopPTZ&value=" + action[1]});
}
function initPTZ(mediaId, instanceId)
{
    var left = '';
    left += '<svg viewBox="0 0 1280 720" xmlns="http://www.w3.org/2000/svg">';
    left += '<g id="' + instanceId + '_left">';
    left += '<line fill="none" stroke-width="2" x1="0" y1="360" x2="100" y2="310" id="svg_leftline1" stroke="#00ff00" opacity="0.5"/>';
    left += '<line fill="none" stroke-width="2" x1="0" y1="360" x2="100" y2="410" id="svg_leftline2" stroke="#00ff00" opacity="0.5"/>';
    left += '<rect x="0" y="310" fill="#fff" opacity="0.2" width="100" height="100"/>';
    left += '</g>';
    left += '<g id="' + instanceId + '_right">';
    left += '<line fill="none" stroke-width="2" x1="1280" y1="360" x2="1180" y2="310" id="svg_rightline1" stroke="#00ff00" opacity="0.5"/>';
    left += '<line fill="none" stroke-width="2" x1="1280" y1="360" x2="1180" y2="410" id="svg_rightline2" stroke="#00ff00" opacity="0.5"/>';
    left += '<rect x="1180" y="310" fill="#fff" opacity="0.2" width="100" height="100"/>';
    left += '</g>';
    left += '<g id="' + instanceId + '_top">';
    left += '<line fill="none" stroke-width="2" x1="640" y1="0" x2="590" y2="100" id="svg_topline1" stroke="#00ff00" opacity="0.5"/>';
    left += '<line fill="none" stroke-width="2" x1="640" y1="0" x2="690" y2="100" id="svg_topline2" stroke="#00ff00" opacity="0.5"/>';
    left += '<rect x="590" y="0" fill="#fff" opacity="0.2" width="100" height="100"/>';
    left += '</g>';
    left += '<g id="' + instanceId + '_bottom">';
    left += '<line fill="none" stroke-width="2" x1="640" y1="720" x2="590" y2="620" id="svg_bottomline1" stroke="#00ff00" opacity="0.5"/>';
    left += '<line fill="none" stroke-width="2" x1="640" y1="720" x2="690" y2="620" id="svg_bottomline2" stroke="#00ff00" opacity="0.5"/>';
    left += '<rect x="590" y="620" fill="#fff" opacity="0.2" width="100" height="100"/>';
    left += '</g>';
    left += '</svg>';
    var f = null;
    var x = document.querySelectorAll("div.ipsContainer.container.nestedEven.ipsMedia");
    for (i = 0; i < x.length; i++) {
        if (x[i].childNodes[1].childNodes[0].childNodes[1].tagName === "IMG") {
            if (x[i].childNodes[1].childNodes[0].childNodes[1].getAttribute("src").includes(mediaId))
            {
                f = x[i].childNodes[1].childNodes[0].childNodes[1];
                i = x.length;
            }
        }
    }

    if (f === -1) {
        x = document.querySelectorAll("div.ipsContainer.container.nestedUneven.ipsMedia");
        for (i = 0; i < x.length; i++) {
            if (x[i].childNodes[1].childNodes[0].childNodes[1].tagName === "IMG") {
                if (x[i].childNodes[1].childNodes[0].childNodes[1].getAttribute("src").includes(mediaId))
                {
                    f = x[i].childNodes[1].childNodes[0].childNodes[1];
                    i = x.length;
                }
            }
        }
    }
    if (f !== null) {
        var style = document.createAttribute("style");
        style.value = "position: absolute; left: 0px; top: 0px; bottom: 0px; right: 0px; outline: solid 2px; padding:10px; z-index: 5;";
        var IpsClass = document.createAttribute("class");
        IpsClass.value = "stream";
        var mydiv = document.createElement("div");
        mydiv.attributes.setNamedItem(style);
        mydiv.attributes.setNamedItem(IpsClass);
        mydiv.innerHTML = left;
        var p = f.parentElement;
        p.appendChild(mydiv);
        document.getElementById(instanceId + "_left").addEventListener("mousedown", sendPTZstart);
        document.getElementById(instanceId + "_right").addEventListener("mousedown", sendPTZstart);
        document.getElementById(instanceId + "_top").addEventListener("mousedown", sendPTZstart);
        document.getElementById(instanceId + "_bottom").addEventListener("mousedown", sendPTZstart);
        document.getElementById(instanceId + "_left").addEventListener("mouseup", sendPTZstop);
        document.getElementById(instanceId + "_right").addEventListener("mouseup", sendPTZstop);
        document.getElementById(instanceId + "_top").addEventListener("mouseup", sendPTZstop);
        document.getElementById(instanceId + "_bottom").addEventListener("mouseup", sendPTZstop);
    }
}

function myPTZRequestActionGet(o)
{
    var oReq = new XMLHttpRequest();
    oReq.addEventListener('loadend', myPTZRequestActionGetLoadEnd);
    oReq.open('GET', o.url, true);
    oReq.send();
}

function myPTZRequestActionGetLoadEnd()
{
    if (this.status >= 200 && this.status < 300)
    {
        if (this.responseText !== "OK") {
            sendError(this.responseText);
        }
    } else {
        sendError(this.statusText);
    }
}

function sendError(data)
{
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
