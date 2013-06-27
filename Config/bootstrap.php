<?php
Configure::write('Stripe.TestSecret', 'sk_test_YUkJtxO4nLRr2qZkWNSOhdOG');
Configure::write('Stripe.LiveSecret', 'sk_live_jPq8Tj9PiyXDtwI2G3UqYulR');
Configure::write('Stripe.mode', 'Test');
Configure::write('Stripe.currency', 'usd');
Configure::write('Stripe.fields', array(
    'stripe_id' => 'id',
    'stripe_last4' => array('card' => 'last4'),
    'stripe_address_zip_check' => array('card' => 'address_zip_check'),
    'stripe_cvc_check' => array('card' => 'cvc_check'),
    'stripe_amount' => 'amount'
));

CakePlugin::load('Opauth', array('routes' => true, 'bootstrap' => true));
Configure::write('Opauth.Strategy.Facebook', array(
   'app_id' => '489011857809805',
   'app_secret' => '936cde3602a722d17f7d01b684b6e3f4',
   'scope' => 'user_location, user_education_history, user_work_history'
));
Configure::write('Opauth.Strategy.LinkedIn', array(
   'api_key' => 'i1yhvah6c8ib',
   'secret_key' => 'TqWiVPuVUnrczE6a',
   'scope' => 'r_fullprofile r_emailaddress'
));