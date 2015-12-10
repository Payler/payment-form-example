var GateUrl = "https://sandbox.payler.com/gapi/";
var SESSION_TYPE = {
    PAY: { value: 1, name: "Pay" },
    BLOCK: { value: 2, name: "Block" }
};

function startSession(sessionType, amount) {
    $.ajax({
        type: "GET",
        url: "/StartSession/" + sessionType.name + "-" + amount,
        success: function (xhr, status) {
            pay(sessionType, xhr);
        },
        error: function (xhr, status, error) {
            $('#errorTxt').text(xhr.responseText + " (" + error + ")");
            if (sessionType == SESSION_TYPE.PAY) {
                $('#buttonBlockSession').show();
            }
            else {
                $('#buttonPaySession').show();
            }
            $('#buttonCharge').show();
            $('#buttonRetrieve').show();
            $('#buttonRefund').show();
            $('#buttonGetStatus').show();
            $('#fieldAmount').show();
        }
    });
}

function charge(amount) {
    $.ajax({
        type: "GET",
        url: "/Charge/" + amount,
        success: function (xhr, status) {
            $('#successTxt').text('OrderId: ' + xhr.order_id + ' ChargeAmount: ' + xhr.amount);
        },
        error: function (xhr, status, error) {
            $('#errorTxt').text(xhr.responseText + " (" + error + ")");
        }
    });
}

function retrieve(amount) {
    $.ajax({
        type: "GET",
        url: "/Retrieve/" + amount,
        success: function (xhr, status) {
            $('#successTxt').text('OrderId: ' + xhr.order_id + ' NewAmount: ' + xhr.new_amount);
        },
        error: function (xhr, status, error) {
            $('#errorTxt').text(xhr.responseText + " (" + error + ")");
        }
    });
}

function refund(amount) {
    $.ajax({
        type: "GET",
        url: "/Refund/" + amount,
        success: function (xhr, status) {
            $('#successTxt').text('OrderId: ' + xhr.order_id + ' NewAmount: ' + xhr.amount);
        },
        error: function (xhr, status, error) {
            $('#errorTxt').text(xhr.responseText + " (" + error + ")");
        }
    });
}

function getStatus() {
    $.ajax({
        type: "GET",
        url: "/GetStatus/",
        success: function (xhr, status) {
            $('#successTxt').text('OrderId: ' + xhr.order_id + ' Amount: ' + xhr.amount + ' Status: ' + xhr.status);
        },
        error: function (xhr, status, error) {
            $('#errorTxt').text(xhr.responseText + " (" + error + ")");
        }
    });
}

function pay(sessionType, paramsObj) {
    var session_id = paramsObj.session_id;
    $('#successTxt').text('Session ID: ' + session_id);

    var sInstruction;
    var button;
    if (sessionType == SESSION_TYPE.PAY) {
        sInstruction = "Оплатить ";
        button = $('#buttonPaySession');
    }
    else {
        sInstruction = "Заблокировать ";
        button = $('#buttonBlockSession');
    }
    button.prop('value', sInstruction + paramsObj.amount / 100 + " р. за товар #" + paramsObj.order_id);

    button.unbind('click').click(function () {
        window.location = GateUrl + "Pay?session_id=" + session_id;
    });
}

$(document).ready(function () {
    $('#buttonPaySession').click(function () {
        buttonClick('buttonPaySession')
    });
    $('#buttonBlockSession').click(function () {
        buttonClick('buttonBlockSession');
    });
    $('#buttonCharge').click(function () {
        clearTxts();
        var amount = document.getElementById("fieldAmount").value;
        charge(amount);
    });
    $('#buttonRetrieve').click(function () {
        clearTxts();
        var amount = document.getElementById("fieldAmount").value;
        retrieve(amount);
    });
    $('#buttonRefund').click(function () {
        clearTxts();
        var amount = document.getElementById("fieldAmount").value;
        refund(amount);
    });
    $('#buttonGetStatus').click(function () {
        clearTxts();
        getStatus();
    });
});

function buttonClick(buttonName) {
    var amount = document.getElementById("fieldAmount").value;
    var bIsPaySession = buttonName == 'buttonPaySession';
    var sessionType = bIsPaySession ? SESSION_TYPE.PAY : SESSION_TYPE.BLOCK;
    if (bIsPaySession) {
        $('#buttonBlockSession').hide();
    }
    else {
        $('#buttonPaySession').hide();
    }
    $('#buttonCharge').hide();
    $('#buttonRetrieve').hide();
    $('#buttonRefund').hide();
    $('#buttonGetStatus').hide();
    $('#fieldAmount').hide();
    clearTxts();
    startSession(sessionType, amount);
}

function clearTxts() {
    $('#errorTxt').text('');
    $('#successTxt').text('');
}