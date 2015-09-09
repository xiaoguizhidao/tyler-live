/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category	Shift4
 * @package		Shift4_Shift4Payment
 * @copyright	Copyright (c) 2011 Shift4 Corporation (http://www.shift4.com)
 * @license		http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
 
jQuery.noConflict();
jQuery('#CardNumber').focus();
var validator = new Validation('payment_form');
var cardsUsed = new Array();
showButtons();

// Pay/Cancel buttons
function showButtons() {
	jQuery('#payButton').click(makePayment).removeClass('disabled');
	jQuery('#cancelButton').click(cancelPayment).removeClass('disabled');
}

function hideButtons() {
	jQuery('#payButton').unbind('click').addClass('disabled');
	jQuery('#cancelButton').unbind('click').addClass('disabled');
}

function makePayment() {
	jQuery('#declinedMessage').hide();
	jQuery('.validation-advice').hide();
	var cc = jQuery('#CardNumber').val().split(' ').join('');
	var ccLength = cc.length;
	var ccFirst8 = cc.slice(0, 8);
	
	if (ccLength >= 16 && ccLength <= 20 && ccFirst8 >= 00000000 && ccFirst8 <= 09999999) {
		jQuery('#CardNumber').removeClass('validate-cc-number');
		jQuery('#ExpirationMonth').removeClass('required-entry');
		jQuery('#ExpirationYear').removeClass('required-entry');
		jQuery('#CVV').removeClass('validate-cc-cvn');
		jQuery('#CVV').removeClass('required-entry');
	}

	if (validator.validate()) {
		var cardNumber = jQuery('#CardNumber').val().split(' ').join('');
		var expirationMonth = jQuery('#ExpirationMonth').val();
		if (expirationMonth < 10) {
			expirationMonth = '0' + expirationMonth;
		}
		var expirationYear = jQuery('#ExpirationYear').val();
		var isDuplicateCard = false;

		if (cardsUsed.length > 0) {
			for (i = 0; i < cardsUsed.length; i++) {
				var cardUsed = cardsUsed[i].split('-');
				if (cardUsed.length == 3 && cardUsed[0] == cardNumber.slice(-4) &&
					cardUsed[1] == expirationMonth &&
					cardUsed[2] == expirationYear.slice(-2)) {
					hideButtons();
					displayError('You cannot use the same card for payment more than once.');
					isDuplicateCard = true;
					break;
				}
			}
		}
		
		if (!isDuplicateCard) {
			hideButtons();
			jQuery('#pleaseWait').show();
			jQuery.ajax({
				type: 'post',
				url: i4GoUrl,
				dataType: 'jsonp',
				data: {
					fuseaction: 'account.jsonpPostCardEntry',
					i4Go_AccountID: i4GoAccountId,
					i4Go_SiteID: i4GoSiteId,
					i4Go_CardNumber: cardNumber,
					i4Go_ExpirationMonth: expirationMonth,
					i4Go_ExpirationYear: expirationYear,
					i4Go_CVV2Code: jQuery('#CVV').val()
				},
				cache: false
			});
		}
	}	

}

function cancelPayment() {
	hideButtons();
	window.location.href = baseUrl + 'shift4payment/processing/cancel/';
}

// Step 1 - Read the response from i4Go, handle any error codes 
function parseResponse(data) {
	if (data.i4go_responsecode != null && data.i4go_responsecode == 1) {
		tokenizeAjax(data);
	} else {
		//displayError(data.i4go_response + ' (Error code: ' + data.i4go_responsecode + ')');
		if (data.i4go_responsecode == 201) {
			displayError('That card type is not accepted. Please use another card.');
		} else if (data.i4go_responsecode == 106 || data.i4go_responsecode == 206) {
			displayError('The expiration date is expired or invalid. Please correct your information and try again.');
		} else if (data.i4go_responsecode >= 298) {
			displayError('There was a problem communicating with the payment gateway. Please try again.');
		} else {
			displayError('The card could not be authorized. Please correct your information and try again.');
		}	
	}
}

// Step 2 - Post Ajax to controller/tokenizeAction to save token as part of the order 
function tokenizeAjax(data) {
	jQuery.ajax({
		type: 'post',
		url: baseUrl + 'shift4payment/processing/tokenize/',
		data: {
			uniqueId: data.i4go_uniqueid,
			cardType: data.i4go_cardtype,
			expirationMonth: data.i4go_expirationmonth,
			expirationYear: data.i4go_expirationyear
		},
		cache: false,
		success: function(data, textStatus, jqXHR) {
			var result = jQuery.parseJSON(data).result;
			if(result != undefined && result.responseCode == 0) {
				processPaymentAjax();
			} else {
				displayError('There was a problem communicating with the payment gateway. Please try again.');
			}
		},
		error: function(jqXHR, textStatus, errorThrown) {
			// This error handler is for problems with submitting the token to Magento.
			displayError('There was an error submitting the payment information. Please try again.');
		}
	});
}

// Step 3 - Post Ajax to controller/processPaymentAction to authorize/capture payment
function processPaymentAjax() {
	jQuery.ajax({
		type: 'post',
		url: baseUrl + 'shift4payment/processing/processpayment/',
		cache: false,
		success: function(data, textStatus, jqXHR) {
			var result = jQuery.parseJSON(data).result;
			if(result != undefined) {
				if (result.responseCode == responseApproved) {
					checkoutSuccess();
				} else if (result.responseCode == responsePartialAuth || result.responseCode == responseCardLimit) {
					displayPartialPayment(result);
				} else if (result.responseCode == responseDeclined) {
					if (result.ccType && result.ccLast4) {
						cardMessage = result.ccType + ' xxxx-' + result.ccLast4;
					} else {
						cardMessage = 'Your card';
					}
					displayError(cardMessage + ' could not be authorized. Please correct your information and try again, or contact us for assistance.');
				} else {
					displayError(result.responseMessage);
				}
			} else {
				displayError('There was an error submitting the payment information. Please try again.');
			}			
		},
		error: function(jqXHR, textStatus, errorThrown) {
			// This error handler is for problems with processing the payment.
			displayError('There was an error submitting the payment information. Please try again.');
		}
	});
}

// Display partial payment message
function displayPartialPayment(paymentInfo) {
	if (paymentInfo.responseCode == responseCardLimit) {
		displayError('You have reached the maximum number of cards allowed to be used for the payment,<br />and the last card entered does not cover the remaining balance.');
	} else {
		cardInfo = paymentInfo.ccLast4 + '-' + paymentInfo.ccExpMonth + '-' + paymentInfo.ccExpYear;
		cardsUsed.push(cardInfo);
		jQuery('#pleaseWait').hide();
		jQuery('#declinedMessage').hide();
		jQuery('#partialAuthCards').append(
			'<div>' + paymentInfo.ccType + ' xxxx-' + paymentInfo.ccLast4 + ' was approved for ' + paymentInfo.processedAmount + '</div>'
		);
		jQuery('#partialAuthRemaining').html('Please enter another card for the remaining ' + paymentInfo.remainingAmount);
		jQuery('#partialAuthMessage').show();
		jQuery('.messages').show();
		jQuery('#CardNumber').val('').focus();
		jQuery('#ExpirationMonth').val('');
		jQuery('#ExpirationYear').val('');
		jQuery('#CVV').val('');
		showButtons();
	}
}

// Display error/timeout
function displayError(message) {
	jQuery('#pleaseWait').hide();
	jQuery('#declinedMessage').html(message);
	jQuery('#declinedMessage').show();
	jQuery('.messages').show();
	jQuery('#CardNumber').val('').focus().addClass('validate-cc-number');
	jQuery('#ExpirationMonth').val('').addClass('required-entry');
	jQuery('#ExpirationYear').val('').addClass('required-entry');
	jQuery('#CVV').val('').addClass('validate-cc-cvn').addClass('required-entry'); 
	showButtons();
}

function checkoutSuccess() {
	window.location.href = baseUrl + 'shift4payment/processing/success';
}

// Card Security Code "What's This?" popup
function toggleToolTip(event){
	if($('payment-tool-tip')){
		$('payment-tool-tip').setStyle({
			left: (Event.pointerX(event)+50)+'px',
			top: (Event.pointerY(event)+10)+'px'
		})
		$('payment-tool-tip').toggle();
	}
	Event.stop(event);
}
if($('payment-tool-tip-close')){
	Event.observe($('payment-tool-tip-close'), 'click', toggleToolTip);
}
$$('.cvv-what-is-this').each(function(element){
	Event.observe(element, 'click', toggleToolTip);
});