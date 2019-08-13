<?php

class Magestore_Autotwitt_Model_Autotwitt extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('autotwitt/autotwitt');
		$this->createTable();
    }
	
	
	/*
		
	*/
	
	public function add_view_product_to_twitter($observer)	{
		
		if (Mage::getStoreConfig('autotwitt/general/enable') && Mage::getStoreConfig('autotwitt/site_events/view_product')){
			
			$today = date("n\/d\/Y");
			
			if (!$this->dayExist($today)) {
				$query = "INSERT INTO autotwitt ( ".
						"day, count ) ".
						"VALUES ( '".$today."', '0' )";
				Mage::getSingleton('core/resource')->getConnection('core_write')->query($query);
			}
			
			if ($this->checkSaveCount($today)) {	
				
				$this->saveCount($today);
				
				$observer_data = $observer->getData();
				$product = $observer_data['product'];	
				$messageContent = Mage::getStoreConfig('autotwitt/general/template_message_view_product');
				
				$messageContent = str_ireplace("{product_name}", $product->getName(), $messageContent);
				$messageContent = str_ireplace("{product_link}", $product->getProductUrl(), $messageContent);
				$messageContent = str_ireplace("{store_link}", Mage::getBaseUrl(), $messageContent);
								
				$this->postToTwitter($messageContent);
				
			} else return;
		} else return;
	}
	
	public function add_order_to_twitter($observer) {
		
		if (mage::getStoreConfig('autotwitt/general/enable') && Mage::getStoreConfig('autotwitt/site_events/place_order_after')) {
			$today = date("n\/d\/Y");
			if (!$this->dayExist($today)) {
				$query = "INSERT INTO autotwitt ( ".
						"day, count ) ".
						"VALUES ( '".$today."', '0' )";
				Mage::getSingleton('core/resource')->getConnection('core_write')->query($query);
			}
			if ($this->checkSaveCount($today)) {
			
				$this->saveCount($today);
				
				$order = $observer->getEvent()->getOrder();
				$messageContent = Mage::getStoreConfig('autotwitt/general/template_message_place_order');
				$messageContent = str_ireplace("{product_links}", $this->getProductLinks(), $messageContent);
				$messageContent = str_ireplace("{store_link}", Mage::getBaseUrl(), $messageContent);
				$messageContent = str_ireplace("{items_count}", count($order->getAllItems()) , $messageContent);
				
				$this->postToTwitter($messageContent);
				
			};
		};
		
	}
	
	public function getProductLinks()
	{
		$items = $this->getQuote()->getAllItems();
		
		$links = array();
		foreach($items as $item)
		{
			
			$links[] = $item->getProduct()->getProductUrl(); 
		}
		
		if(count($links))
		{
			return implode(",", $links);
		}
		else
		{
			$links = "";
		}
		
		return $links;
		
	}

	public function add_product_to_twitter($observer){
		
		if (Mage::getStoreConfig('autotwitt/general/enable') && Mage::getStoreConfig('autotwitt/site_events/add_to_cart')) {
			$today = date("n\/d\/Y");
			if (!$this->dayExist($today)) {
				$query = "INSERT INTO autotwitt ( ".
						"day, count ) ".
						"VALUES ( '".$today."', '0' )";
				Mage::getSingleton('core/resource')->getConnection('core_write')->query($query);
			}
			if ($this->checkSaveCount($today)) {
			
				$this->saveCount($today);
				
				$observer_data = $observer->getData();
				$product = $observer_data['product'];	
				$messageContent = Mage::getStoreConfig('autotwitt/general/template_message_add_cart');
				$messageContent = str_ireplace("{product_name}", $product->getName(), $messageContent);
				$messageContent = str_ireplace("{product_link}", $product->getProductUrl(), $messageContent);
				$messageContent = str_ireplace("{store_link}", Mage::getBaseUrl(), $messageContent);
				
				$this->postToTwitter($messageContent);
				
				
				
			} else return;
		} else return;
	}
	
	function postToTwitter($message){
		$username = Mage::getStoreConfig('autotwitt/account/username');
		$password = Mage::getStoreConfig('autotwitt/account/password');
		
		$host = "http://twitter.com/statuses/update.xml?status=".urlencode(stripslashes(urldecode($message)));

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $host);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLOPT_POST, 1);

		$result = curl_exec($ch);
		// Look at the returned header
		$resultArray = curl_getinfo($ch);
		
		curl_close($ch);

		if($resultArray['http_code'] == "200"){
			 $twitter_status='Your message has been sended! <a href="http://twitter.com/'.$username.'">See your profile</a>';
		} else {
			 $twitter_status="Error posting to Twitter. Retry";
		}
		return $twitter_status;
	}
	
	
	
	// create testtable table to save count
	function createTable() {
		$query = "CREATE TABLE IF NOT EXISTS autotwitt (".
				 "ID int AUTO_INCREMENT,".
				 "day varchar(255),".
				 "count int, ".
				 "PRIMARY KEY (ID) ".
				 ")";
		$db = Mage::getSingleton('core/resource')->getConnection('core_read');
		$rs = $db->query($query);
	}
	
	// save counts in a day
	function saveCount($today) {
		
		//get count in database
		$db = Mage::getSingleton('core/resource')->getConnection('core_read');
		$query = "SELECT * FROM autotwitt WHERE day = '".$today."'";
		$rs = $db->fetchAll($query);
		$count = $rs[0]['count'];
		
		//save count to database
		$count++;
		$db = Mage::getSingleton('core/resource')->getConnection('core_write');
		$query = "UPDATE autotwitt ".
				"SET count = '".$count."' ".
				"WHERE day = '".$today."'";
		$db->query($query);
			
	}
	
	//check today exist or not exist
	function dayExist ($today) {
		$query = "SELECT * FROM autotwitt WHERE day = '".$today."'";
		$db = Mage::getSingleton('core/resource')->getConnection('core_read');
		$rs = $db->fetchAll($query);
		if ($rs == null) {
			return false;
		} else 
			return true;
	}
	
	//check day frequency and week frequency
	function checkSaveCount($today) {		
		$query = "SELECT * FROM autotwitt WHERE day = '".$today."'";
		$db = Mage::getSingleton('core/resource')->getConnection('core_read');
		$rs = $db->fetchAll($query);
		
		$count = $rs[0]['count'];
		
		$dayFreq = Mage::getStoreConfig('autotwitt/general/day_frequency');
		$weekFreq = Mage::getStoreConfig('autotwitt/general/week_frequency');

		if ($this->sumWeekCount($today) < $weekFreq) {
			if ($count < $dayFreq) {
				return true;
			} else return false;
		} else return false;
		
	}
	
	// sum counts in a week from today to past
	function sumWeekCount($today) {
		$i = 0;
		$sum = 0;
		$_today = explode("/", $today);
		$day = $_today[1];
		$month = $_today[0];
		$year = $_today[2];
		$todayjd = cal_to_jd(CAL_GREGORIAN, $month, $day, $year);
		
		while (true) {
			$i++;
			$before = cal_from_jd($todayjd, CAL_GREGORIAN);
			$query = "SELECT * FROM autotwitt WHERE day = '".$before['date']."'";
			$db = Mage::getSingleton('core/resource')->getConnection('core_read');
			$rs = $db->fetchAll($query);
			if ($rs == null || $i == 7) {
				break;
			} else {
				$dayCount = $rs[0]['count'];
				$sum += $dayCount;
				$todayjd--;
			}
		}
		return $sum;
	}
	
	 public function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }
	
	 public function getQuote()
    {
        return $this->getCheckoutSession()->getQuote();
    }
}