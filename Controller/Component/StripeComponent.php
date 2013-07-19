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
		} else {
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
 * The createCard method prepares data for Stripe_Card::create and attempts to
 * create a new customer.
 *
 * @param array	$data Must contain 'email'.
 * @return array $customer if success, string $error if failure.
 * @throws CakeException
 * @throws CakeException
 * @throws CakeException
 *
 */
	public function createCard($data) {
		// set the Stripe API key
		$key = Configure::read('Stripe.' . $this->mode . 'Secret');
		if (!$key) {
			throw new CakeException('Stripe API key is not set.');
		}

		Stripe::setApiKey($key);
		$error = null;
		try {
			// GET CUSTOMER INFO
			$customer = Stripe_Customer::retrieve($data['customer']);

			// ADD CARD
			$customer->card = $data['card'];

			// SAVE CUSTOMER
			$customer = $customer->save();

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

		CakeLog::info('Stripe: updated customer id ' . $customer->id, 'stripe');

		return $this->_formatResult('new_card', $customer);
	}


/**
 * The createCustomer method prepares data for Stripe_Customer::create and attempts to
 * create a new customer.
 *
 * @param array	$data Must contain 'email'.
 * @return array $customer if success, string $error if failure.
 * @throws CakeException
 * @throws CakeException
 * @throws CakeException
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
			if(isset($data['stripeToken'])) {
				$customer = Stripe_Customer::create(array(
					'card' => $data['stripeToken'],
					'email' => $data['email']
				));
			} else {
				$customer = Stripe_Customer::create(array(
					'email' => $data['email']
				));
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

		CakeLog::info('Stripe: new customer id ' . $customer->id, 'stripe');

		return $this->_formatResult('customer', $customer);
	}


/**
 * The createCard method prepares data for Stripe_Card::create and attempts to
 * create a new customer.
 *
 * @param array	$data Must contain 'email'.
 * @return array $customer if success, string $error if failure.
 * @throws CakeException
 * @throws CakeException
 * @throws CakeException
 *
 */
	public function getCards($data) {
		// set the Stripe API key
		$key = Configure::read('Stripe.' . $this->mode . 'Secret');
		if (!$key) {
			throw new CakeException('Stripe API key is not set.');
		}

		Stripe::setApiKey($key);
		$error = null;
		try {
			// GET CUSTOMER INFO
			$customer = Stripe_Customer::retrieve($data['customer']);

			var_dump($customer->cards['data']);exit;

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

		CakeLog::info('Stripe: updated customer id ' . $customer->id, 'stripe');

		return $this->_formatResult('new_card', $customer);
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
			return array($type => $data);
		}
	}

}