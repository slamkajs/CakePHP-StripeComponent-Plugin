<?php
Configure::write('Stripe.TestSecret', 'sk_test_YUkJtxO4nLRr2qZkWNSOhdOG');
Configure::write('Stripe.LiveSecret', 'sk_live_jPq8Tj9PiyXDtwI2G3UqYulR');
Configure::write('Stripe.mode', 'Test');
Configure::write('Stripe.currency', 'usd');
Configure::write('Stripe.fields', array(
	'charge' => array(
	    'stripe_id' => 'id',
	    'stripe_last4' => array('card' => 'last4'),
	    'stripe_address_zip_check' => array('card' => 'address_zip_check'),
	    'stripe_cvc_check' => array('card' => 'cvc_check'),
	    'stripe_amount' => 'amount',
	    'stripe_customer' => 'customer'),
    'customer' => array(
        'stripe_id' => 'id'),
    'new_card' => array(
        'stripe_id' => 'id',
        'cards' => array('cards' => 'data'))));