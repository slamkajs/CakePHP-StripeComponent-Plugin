<?php
/**
 * StripeComponent
 *
 * A component that handles payment processing using Stripe.
 *
 * PHP version 5
 *
 * @package		StripeComponent
 * @author		Gregory Gaskill <gregory@chronon.com>
 * @license		MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @link		https://github.com/chronon/CakePHP-StripeComponent-Plugin
 */

App::uses('Component', 'Controller');

/**
 * StripeComponent
 *
 * @package		StripeComponent
 */
class StripeComponent extends Component {

/**
 * Default Stripe mode to use: Test or Live
 *
 * @var string
 * @access public
 */
	public $mode = 'Test';

/**
 * Default currency to use for the transaction
 *
 * @var string
 * @access public
 */
	public $currency = 'usd';

/**
 * Default mapping of fields to be returned: local_field => stripe_field
 *
 * @var array
 * @access public
 */
	public $fields = array('stripe_id' => 'id');

/**
 * Controller startup. Loads the Stripe API library and sets options from
 * APP/Config/bootstrap.php.
 *
 * @param Controller $controller Instantiating controller
 * @return void
 * @throws CakeException
 */
	public function startup(Controller $controller) {
		$this->Controller = $controller;

		// load the stripe vendor class IF it hasn't been autoloaded (composer)
		App::import('Vendor', 'Stripe.Stripe', array(
			'file' => 'Stripe' . DS . 'lib' . DS . 'Stripe.php')
		);
		if (!class_exists('Stripe')) {
			throw new CakeException('Stripe API Libaray is missing or could not be loaded.');
		}

		// if mode is set in bootstrap.php, use it. otherwise, Test.
		$mode = Configure::read('Stripe.mode');
		if ($mode) {
			$this->mode = $mode;
		}

		// if currency is set in bootstrap.php, use it. otherwise, usd.
		$currency = Configure::read('Stripe.currency');
		if ($currency) {
			$this->currency = $currency;
		}

		// field map for charge response, or use default (set above)
		$fields = Configure::read('Stripe.fields');
		if ($fields) {
			$this->fields = $fields;
		}
	}


/**
 * The addCard method prepares data for Stripe_Customer::save and attempts to
 * add a new card to an existing customer.
 *
 */
	public function addCard($data) {
		// set the Stripe API key
		$key = Configure::read('Stripe.' . $this->mode . 'Secret');
		if (!$key) {
			throw new CakeException('Stripe API key is not set.');
		}


		Stripe::setApiKey($key);
		$error = null;
		try {

			$customer = Stripe_Customer::retrieve($data['customer']);

			$customer->cards->create(array('card' => $data['card']));

		} catch(Stripe_CardError $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			CakeLog::error('Stripe: ' . $err['type'] . ': ' . $err['code'] . ': ' . $err['message'], 'stripe');
			$error = $err['message'];

		} catch (Stripe_InvalidRequestError $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			CakeLog::error('Stripe: ' . $err['type'] . ': ' . $err['message'], 'stripe');
			$error = $err['message'];

		} catch (Stripe_AuthenticationError $e) {
			CakeLog::error('Stripe: API key rejected!', 'stripe');
			$error = 'Payment processor API key error.';

		} catch (Stripe_Error $e) {
			CakeLog::error('Stripe: Stripe_Error - Stripe could be down.', 'stripe');
			$error = 'Payment processor error, try again later.';

		} catch (Exception $e) {
			CakeLog::error('Stripe: Unknown error.', 'stripe');
			$error = 'There was an error, try again later.';
		}

		if ($error !== null) {
			// an error is always a string
			return (string)$error;
		}

		CakeLog::info('Stripe: new customer id ' . $customer->id, 'stripe');

		return $this->_formatResult('customer', $customer);
	}

/**
 * The charge method prepares data for Stripe_Charge::create and attempts a
 * transaction.
 *
 * @param array	$data Must contain 'amount' and 'stripeToken'.
 * @return array $charge if success, string $error if failure.
 * @throws CakeException
 * @throws CakeException
 * @throws CakeException
 */
	public function charge($data) {
		// set the Stripe API key
		$key = Configure::read('Stripe.' . $this->mode . 'Secret');
		if (!$key) {
			throw new CakeException('Stripe API key is not set.');
		}

		// $data MUST contain 'amount' and 'stripeToken' to make a charge.
		if (!isset($data['amount']) || (!isset($data['stripeToken']) && !isset($data['customer']))) {
			throw new CakeException('The required amount or stripeToken fields are missing.');
		}

		// if supplied amount is not numeric, abort.
		if (!is_numeric($data['amount'])) {
			throw new CakeException('Amount must be numeric.');
		}
		
		// set the (optional) description field to null if not set in $data
		if (!isset($data['description'])) {
			$data['description'] = null;
		}

		// format the amount, in cents.
		$data['amount'] = $data['amount'] * 100;

		$cardOrCustomer = array();
		if(isset($data['stripeToken'])) {
			$cardOrCustomer['card'] = $data['stripeToken'];
		} 

		if(isset($data['customer'])){
			$cardOrCustomer['customer'] = $data['customer'];
		}

		Stripe::setApiKey($key);
		$error = null;
		try {
			$charge = Stripe_Charge::create(array_merge(array(
				'amount' => $data['amount'],
				'currency' => $this->currency,
				'description' => $data['description']
			), $cardOrCustomer));

		} catch(Stripe_CardError $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			CakeLog::error('Stripe: ' . $err['type'] . ': ' . $err['code'] . ': ' . $err['message'], 'stripe');
			$error = $err['message'];

		} catch (Stripe_InvalidRequestError $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			CakeLog::error('Stripe: ' . $err['type'] . ': ' . $err['message'], 'stripe');
			$error = $err['message'];

		} catch (Stripe_AuthenticationError $e) {
			CakeLog::error('Stripe: API key rejected!', 'stripe');
			$error = 'Payment processor API key error.';

		} catch (Stripe_Error $e) {
			CakeLog::error('Stripe: Stripe_Error - Stripe could be down.', 'stripe');
			$error = 'Payment processor error, try again later.';

		} catch (Exception $e) {
			CakeLog::error('Stripe: Unknown error.', 'stripe');
			$error = 'There was an error, try again later.';
		}

		if ($error !== null) {
			// an error is always a string
			return (string)$error;
		}

		CakeLog::info('Stripe: charge id ' . $charge->id, 'stripe');

		return $this->_formatResult('charge', $charge);
	}


/**
 * The createCustomer method prepares data for Stripe_Customer::create and attempts to
 * create a new customer.
 *
 */
	public function createCustomer($data) {
		// set the Stripe API key
		$key = Configure::read('Stripe.' . $this->mode . 'Secret');
		if (!$key) {
			throw new CakeException('Stripe API key is not set.');
		}

		Stripe::setApiKey($key);
		$error = null;
		try {
			$stripeData = array(
				'card' => (isset($data['stripeToken']) ? $data['stripeToken'] : ''),
				'email' => $data['email']
			);

			if(empty($stripeData['card'])) unset($stripeData['card']);

			$customer = Stripe_Customer::create($stripeData);

		} catch(Stripe_CardError $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			CakeLog::error('Stripe: ' . $err['type'] . ': ' . $err['code'] . ': ' . $err['message'], 'stripe');
			$error = $err['message'];

		} catch (Stripe_InvalidRequestError $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			CakeLog::error('Stripe: ' . $err['type'] . ': ' . $err['message'], 'stripe');
			$error = $err['message'];

		} catch (Stripe_AuthenticationError $e) {
			CakeLog::error('Stripe: API key rejected!', 'stripe');
			$error = 'Payment processor API key error.';

		} catch (Stripe_Error $e) {
			CakeLog::error('Stripe: Stripe_Error - Stripe could be down.', 'stripe');
			$error = 'Payment processor error, try again later.';

		} catch (Exception $e) {
			CakeLog::error('Stripe: Unknown error.', 'stripe');
			$error = 'There was an error, try again later.';
		}

		if ($error !== null) {
			// an error is always a string
			return (string)$error;
		}

		CakeLog::info('Stripe: new customer id ' . $customer->id, 'stripe');

		return $this->_formatResult('customer', $customer);
	}
/**
 * The getCards method attempts to retrieve a list of cards for provided customer
 * add a new card to an existing customer.
 *
 */
	public function getCards($customer) {
		// set the Stripe API key
		$key = Configure::read('Stripe.' . $this->mode . 'Secret');
		if (!$key) {
			throw new CakeException('Stripe API key is not set.');
		}

		Stripe::setApiKey($key);
		$error = null;
		try {

			$cards = Stripe_Customer::retrieve($customer)->cards->all();

			$cards_formatted = array();

			// FORMAT CARDS
			foreach ($cards->data as $cnt => $card) {
				$cards_formatted[$cnt] = array(
					'card_id' => $card->id,
					'last4' => $card->last4,
					'type' => $card->type,
					'name' => $card->name,
					'expiration' => sprintf('%s/%s', $card->exp_month, $card->exp_year),
					'addr_line1' => $card->address_line1,
					'addr_line2' => $card->address_line2,
					'addr_city' => $card->address_city,
					'addr_state' => $card->address_state,
					'addr_country' => $card->country,
					'addr_zip' => $card->address_zip);
			}

		} catch(Stripe_CardError $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			CakeLog::error('Stripe: ' . $err['type'] . ': ' . $err['code'] . ': ' . $err['message'], 'stripe');
			$error = $err['message'];

		} catch (Stripe_InvalidRequestError $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			CakeLog::error('Stripe: ' . $err['type'] . ': ' . $err['message'], 'stripe');
			$error = $err['message'];

		} catch (Stripe_AuthenticationError $e) {
			CakeLog::error('Stripe: API key rejected!', 'stripe');
			$error = 'Payment processor API key error.';

		} catch (Stripe_Error $e) {
			CakeLog::error('Stripe: Stripe_Error - Stripe could be down.', 'stripe');
			$error = 'Payment processor error, try again later.';

		} catch (Exception $e) {
			CakeLog::error('Stripe: Unknown error.', 'stripe');
			$error = 'There was an error, try again later.';
		}

		if ($error !== null) {
			// an error is always a string
			return (string)$error;
		}

		CakeLog::info('Stripe: retrieving cards for customer id ' . $customer, 'stripe');

		return (isset($cards_formatted) ? $cards_formatted : array());
	}

/**
 * The refund method attempts to refund a charge
 *
 * @param array	$data Must contain 'amount' and 'stripeToken'.
 * @return array $charge if success, string $error if failure.
 * @throws CakeException
 * @throws CakeException
 * @throws CakeException
 */
	public function refund($data) {
		// set the Stripe API key
		$key = Configure::read('Stripe.' . $this->mode . 'Secret');
		if (!$key) {
			throw new CakeException('Stripe API key is not set.');
		}

		// $data MUST contain 'amount' and 'stripeToken' to make a charge.
		if (!isset($data['charge_id'])) {
			throw new CakeException('The charge id is missing.');
		}

		// if supplied amount is not numeric, abort.
		if (empty($data['charge_id'])) {
			throw new CakeException('The charge id is empty.');
		}

		Stripe::setApiKey($key);
		$error = null;
		try {
			$charge = Stripe_Charge::retrieve($data['charge_id']);
			$charge->refund();

		} catch(Stripe_CardError $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			CakeLog::error('Stripe: ' . $err['type'] . ': ' . $err['code'] . ': ' . $err['message'], 'stripe');
			$error = $err['message'];

		} catch (Stripe_InvalidRequestError $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			CakeLog::error('Stripe: ' . $err['type'] . ': ' . $err['message'], 'stripe');
			$error = $err['message'];

		} catch (Stripe_AuthenticationError $e) {
			CakeLog::error('Stripe: API key rejected!', 'stripe');
			$error = 'Payment processor API key error.';

		} catch (Stripe_Error $e) {
			CakeLog::error('Stripe: Stripe_Error - Stripe could be down.', 'stripe');
			$error = 'Payment processor error, try again later.';

		} catch (Exception $e) {
			CakeLog::error('Stripe: Unknown error.', 'stripe');
			$error = 'There was an error, try again later.';
		}

		if ($error !== null) {
			// an error is always a string
			return (string)$error;
		}

		CakeLog::info('Stripe: refunded charge id ' . $charge->id, 'stripe');

		return $this->_formatResult('refund', $charge);
	}

/**
 * Returns an array of fields we want from Stripe's charge object
 *
 *
 * @param object $charge A successful charge object.
 * @return array The desired fields from the charge object as an array.
 */
	protected function _formatResult($type, $data) {
		$result = array();

		if(isset($this->fields[$type])) {
			foreach ($this->fields[$type] as $local => $stripe) {
				if (is_array($stripe)) {
					foreach ($stripe as $obj => $field) {
						$result[$local] = $data->$obj->$field;
					}
				} else {
					$result[$local] = $data->$stripe;
				}
			}
			return $result;
		} else {
			foreach ($data as $cnt => $elem) {
				var_dump($data[$cnt]);
			}
			exit;
			return array($type => $data);
		}
	}

}