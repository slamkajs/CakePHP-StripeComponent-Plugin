<?php
Configure::write('Stripe.TestSecret', getenv('STRIPE_KEY'));
Configure::write('Stripe.LiveSecret', getenv('STRIPE_KEY'));
Configure::write('Stripe.mode', getenv('STRIPE_MODE'));
Configure::write('Stripe.currency', 'usd');
Configure::write('Stripe.fields', array(
	'addCard' => array(
		'stripe_id' => 'id'),
	'charge' => array(
	    'stripe_id' => 'id',
	    'stripe_last4' => array('card' => 'last4'),
	    'stripe_address_zip_check' => array('card' => 'address_zip_check'),
	    'stripe_cvc_check' => array('card' => 'cvc_check'),
	    'stripe_amount' => 'amount',
	    'stripe_customer' => 'customer'),
	'customer' => array(
		'stripe_id' => 'id'),
	'refund' => array(
	    'stripe_id' => 'id',
	    'stripe_last4' => array('card' => 'last4'),
	    'stripe_card-type' => array('card' => 'type'),
	    'stripe_amount' => 'amount_refunded',
	    'stripe_customer' => 'customer')));