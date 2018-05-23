<?php

  use \Psr\Http\Message\ServerRequestInterface as Request;
  use \Psr\Http\Message\ResponseInterface as Response;

  use Monolog\Logger;
  use Monolog\Handler\StreamHandler;

  $container = $app->getContainer();

  $container['logger'] = function ($c) {
      // create a log channel
      $log = new Logger('api');
      $log->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log', Logger::INFO));

      return $log;
  };

  /**
   * This method restricts access to addresses. <br/>
   * <b>post: </b>To access is required a valid token.
   */
  $app->add(new \Slim\Middleware\JwtAuthentication([
      // The secret key
      "secret" => SECRET,
      "rules" => [
          new \Slim\Middleware\JwtAuthentication\RequestPathRule([
              // Degenerate access to '/webresources'
              "path" => "/webresources",
              // It allows access to 'login' without a token
              "passthrough" => [
                "/webresources/mobile_app/ping",
                "/webresources/mobile_app/login",
                "/webresources/mobile_app/register"
                ]
          ])
      ]
  ]));

  /**
   * This method a url group. <br/>
   * <b>post: </b>establishes the base url '/public/webresources/mobile_app/'.
   */
  $app->group('/webresources/mobile_app', function () use ($app) {
    /**
     * This method is used for testing the api.<br/>
     * <b>post: </b> http://localhost/api/public/webresources/mobile_app/ping
     */
    $app->get('/ping', function (Request $request, Response $response) {
		 $test = LookupData::getLookupValue("ext_tax_map","tax_id","ext_tax_id","11403");
		 $this['logger']->info("Test".$test);
      return "pong";
    });

    /**
     * This method gets a user into the database.
     * @param string $user - username
     * @param string $pass - password
     */
    $app->get('/login/{user}/{password}', function (Request $request, Response $response) {
      // Gets username and password
      $user = $request->getAttribute("user");
      $pass = $request->getAttribute("password");

      // Gets the database connection
      $conn = PDOConnection::getConnection();

  		try {
  			// Gets the user into the database
  			$sql = "SELECT * FROM USERS WHERE USERNAME=:user";
  			$stmt = $conn->prepare($sql);
  			$stmt->bindParam(":user", $user);
  			$stmt->execute();
  			$query = $stmt->fetchObject();

  			// If user exist
  			if ($query) {
  				// If password is correct
  				if (password_verify($pass, $query->PASSWORD)) {
            // Create a new resource
            $data['token'] = JWTAuth::getToken($query->ID_USER, $query->USERNAME);
          } else {
  					// Password wrong
            $data['status'] = "Error: The password you have entered is wrong.";
  				}
  			} else {
  				// Username wrong
          $data['status'] = "Error: The user specified does not exist.";
  			}

        // Return the result
        $response = $response->withHeader('Content-Type','application/json');
        $response = $response->withStatus(200);
        $response = $response->withJson($data);
        return $response;
      } catch (PDOException $e) {
        $this['logger']->error("DataBase Error.<br/>" . $e->getMessage());
      } catch (Exception $e) {
        $this['logger']->error("General Error.<br/>" . $e->getMessage());
      } finally {
        // Destroy the database connection
        $conn = null;
      }
    });

    /**
     * This method gets a user into the database.
     * @param string $user - username
     * @param string $pass - password
     * @param int $country - country id
     */
    $app->get('/register/{user}/{password}/{country}', function (Request $request, Response $response) {
  		// Unique ID
		  $guid = uniqid();
      // Gets username and password
      $user = $request->getAttribute("user");
      $pass = password_hash($request->getAttribute("password"), PASSWORD_DEFAULT);
      // Date of created
      $created = date('Y-m-d');
      // Country ID
      $country = (int) $request->getAttribute("country");

      // Gets the database connection
      $conn = PDOConnection::getConnection();

  		try {
  			// Gets the user into the database
  			$sql = "INSERT INTO USERS(GUID, USERNAME, PASSWORD, CREATED_AT, ID_COUNTRY) VALUES(:guid, :user, :pass, :created, :country)";
  			$stmt = $conn->prepare($sql);
  			$stmt->bindParam(":guid", $guid);
  			$stmt->bindParam(":user", $user);
        $stmt->bindParam(":pass", $pass);
        $stmt->bindParam(":created", $created);
        $stmt->bindParam(":country", $country);
  			$result = $stmt->execute();

        // If user has been registered
  			if ($result) {
          $data['status'] = "Your account has been successfully created.";
  			} else {
          $data['status'] = "Error: Your account cannot be created at this time. Please try again later.";
        }
        
        $response = $response->withHeader('Content-Type','application/json');
        $response = $response->withStatus(200);
        $response = $response->withJson($data);
        return $response;
      } catch (PDOException $e) {
        $this['logger']->error("DataBase Error.<br/>" . $e->getMessage());
      } catch (Exception $e) {
        $this['logger']->error("General Error.<br/>" . $e->getMessage());
      } finally {
        // Destroy the database connection
        $conn = null;
      }
    });

    /**
     * This method cheks the token.
     */
    $app->get('/verify', function (Request $request, Response $response) {
      // Gets the token of the header.
      $token = str_replace('Bearer ', '', $request->getServerParams()['HTTP_AUTHORIZATION']);
      // Verify the token.
      $result = JWTAuth::verifyToken($token);
      // Return the result
      $data['status'] = $result;
      $response = $response->withHeader('Content-Type','application/json');
      $response = $response->withStatus(200);
      $response = $response->withJson($data);
      return $response;
    });

    /**
     * This method publish short text messages of no more than 120 characters
     * @param string $quote - The text of post
     * @param int $id - The user id
     */
    $app->post('/post', function (Request $request, Response $response) {
      // Gets quote and user id
      $quote = $request->getParam('quote');
      $id = $request->getParam('id');

      // Gets the database connection
      $conn = PDOConnection::getConnection();

      try {
        // Gets the user into the database
  			$sql = "SELECT * FROM USERS WHERE ID_USER=:id";
  			$stmt = $conn->prepare($sql);
  			$stmt->bindParam(":id", $id);
  			$stmt->execute();
  			$query = $stmt->fetchObject();

  			// If user exist
  			if ($query) {
          // Truncate the text
          if (strlen($quote) > 120) {
            $quote = substr($quote, 0, 120);
          }

          // Insert post into the database
          $sql = "INSERT INTO QUOTES(QUOTE, ID_USER) VALUES(:quote, :id)";
          $stmt = $conn->prepare($sql);
          $stmt->bindParam(":quote", $quote);
          $stmt->bindParam(":id", $id);
          $result = $stmt->execute();

          $data['status'] = $result;
        } else {
          // Username wrong
          $data['status'] = "Error: The user specified does not exist.";
  			}
        
        // Return the result
        $response = $response->withHeader('Content-Type','application/json');
        $response = $response->withStatus(200);
        $response = $response->withJson($data);
        return $response;
      } catch (PDOException $e) {
        $this['logger']->error("DataBase Error.<br/>" . $e->getMessage());
      } catch (Exception $e) {
        $this['logger']->error("General Error.<br/>" . $e->getMessage());
      } finally {
        // Destroy the database connection
        $conn = null;
      }
    });

    /**
     * This method list the latest published messages
     */
    $app->get('/list', function (Request $request, Response $response) {
      // Gets the database connection
      $conn = PDOConnection::getConnection();

      try {
        // Gets the posts into the database
        $sql = "SELECT Q.ID_QUOTE AS id, Q.QUOTE AS quote, Q.POST_DATE AS postdate, Q.LIKES AS likes, U.USERNAME AS user FROM QUOTES AS Q, USERS AS U WHERE Q.ID_USER=U.ID_USER ORDER BY likes DESC";
        $stmt = $conn->query($sql);
        $data = $stmt->fetchAll();

        // Return a list
        $response = $response->withHeader('Content-Type','application/json');
        $response = $response->withStatus(200);
        $response = $response->withJson($data);
        return $response;
      } catch (PDOException $e) {
        $this['logger']->error("DataBase Error.<br/>" . $e->getMessage());
      } catch (Exception $e) {
        $this['logger']->error("General Error.<br/>" . $e->getMessage());
      } finally {
        // Destroy the database connection
        $conn = null;
      }
    });

    /**
     * This method list the users for likes
     * @param int $id - quote id
     */
    $app->get('/likes/{id}', function (Request $request, Response $response) {
      // Gets quote
      $id = $request->getAttribute('id');

      // Gets the database connection
      $conn = PDOConnection::getConnection();

      try {
        // Gets the posts into the database
        $sql = "SELECT U.GUID AS guid, U.USERNAME AS user FROM LIKES AS L, USERS AS U WHERE L.ID_USER = U.ID_USER AND L.ID_QUOTE = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $data = $stmt->fetchAll();

        // Return a list
        $response = $response->withHeader('Content-Type','application/json');
        $response = $response->withStatus(200);
        $response = $response->withJson($data);
        return $response;
      } catch (PDOException $e) {
        $this['logger']->error("DataBase Error.<br/>" . $e->getMessage());
      } catch (Exception $e) {
        $this['logger']->error("General Error.<br/>" . $e->getMessage());
      } finally {
        // Destroy the database connection
        $conn = null;
      }
    });

    /**
     * This method searches for messages by your text.
     * @param string $quote - The text of post
     */
    $app->get('/search/{quote}', function (Request $request, Response $response) {
      // Gets quote
      $quote = '%' . $request->getAttribute('quote') . '%';

      // Gets the database connection
      $conn = PDOConnection::getConnection();

      try {
        // Search into the database
        $sql = "SELECT Q.ID_QUOTE AS id, Q.QUOTE AS quote, Q.POST_DATE AS postdate, Q.LIKES AS likes, U.USERNAME AS user FROM QUOTES AS Q, USERS AS U WHERE QUOTE LIKE :quote AND Q.ID_USER=U.ID_USER ORDER BY likes DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':quote', $quote);
        $stmt->execute();
        $data = $stmt->fetchAll();

        // Return the result
        $response = $response->withHeader('Content-Type','application/json');
        $response = $response->withStatus(200);
        $response = $response->withJson($data);
        return $response;
      } catch (PDOException $e) {
        $this['logger']->error("DataBase Error.<br/>" . $e->getMessage());
      } catch (Exception $e) {
        $this['logger']->error("General Error.<br/>" . $e->getMessage());
      } finally {
        // Destroy the database connection
        $conn = null;
      }
    });

    /**
     * This method deletes a specific message by its id.
     * @param Int $id - The quote id
     */
    $app->delete('/delete', function (Request $request, Response $response) {
      // Gets quote id
      $id = $request->getParam('id');

      // Gets the database connection
      $conn = PDOConnection::getConnection();

      try {
        // Delete the quote
        $sql = "DELETE FROM QUOTES WHERE ID_QUOTE=:id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $result = $stmt->execute();

        // Return the result
        $data['status'] = $result;
        
        $response = $response->withHeader('Content-Type','application/json');
        $response = $response->withStatus(200);
        $response = $response->withJson($data);
        return $response;
      } catch (PDOException $e) {
        $this['logger']->error("DataBase Error.<br/>" . $e->getMessage());
      } catch (Exception $e) {
        $this['logger']->error("General Error.<br/>" . $e->getMessage());
      } finally {
        // Destroy the database connection
        $conn = null;
      }
    });
	
//Please do not touch above lines, learning code.
	 /**
     * This method create  record for client.
     * @param string $quote - The text of post
     * @param int $id - The user id
     */
    $app->post('/clients', function (Request $request, Response $response) {
      // Gets quote and user id
     
	        $ext_client_id=$request->getParam('ext_client_id');
			$client_name=$request->getParam('client_name');
            $client_address_1=$request->getParam('client_address_1');
            $client_address_2=$request->getParam('client_address_2');
            $client_city=$request->getParam('client_city');
            $client_state=$request->getParam('client_state');
            $client_zip=$request->getParam('client_zip');
            $client_country=$request->getParam('client_country');
            $client_phone=$request->getParam('client_phone');
            $client_fax=$request->getParam('client_fax');
            $client_mobile=$request->getParam('client_mobile');
            $client_email=$request->getParam('client_email');
            $client_web=$request->getParam('client_web');
            $client_vat_id=$request->getParam('client_vat_id');
            $client_tax_code=$request->getParam('client_tax_code');
            $client_active=1;

      // Gets the database connection
      $conn = PDOConnection::getConnection();

      try {
        // Gets the user into the database
  			$sql = "SELECT * FROM ext_client_map where 	ext_client_id=:ext_client_id";
  			$stmt = $conn->prepare($sql);
  			$stmt->bindParam(":ext_client_id", $ext_client_id);
  			$stmt->execute();
  			$query = $stmt->fetchObject();

  			// If user exist
  			if ($query) {
                $data['status'] = "Client Aleady Exist";
        } else {
			
          // Insert Data for Client Who is not exist:
     
          $sql = "INSERT INTO ip_clients(client_name, client_address_1, client_address_2,client_city,client_state,client_zip,client_phone,client_fax,client_mobile,client_email,client_web,client_vat_id,client_tax_code,client_active) VALUES(:client_name, :client_address_1,:client_address_2,:client_city,:client_state,:client_zip,:client_phone,:client_fax,:client_mobile,:client_email,:client_web,:client_vat_id,:client_tax_code,:client_active)";
			  $stmt = $conn->prepare($sql);
			  $stmt->bindParam(":client_name", $client_name);
			  $stmt->bindParam(":client_address_1", $client_address_1);
			  $stmt->bindParam(":client_address_2", $client_address_2);
			  $stmt->bindParam(":client_city", $client_city);
			  $stmt->bindParam(":client_state", $client_state);
			  $stmt->bindParam(":client_zip", $client_zip);
			  $stmt->bindParam(":client_phone", $client_phone);
			  $stmt->bindParam(":client_fax", $client_fax);
			  $stmt->bindParam(":client_mobile", $client_mobile); 
			  $stmt->bindParam(":client_email", $client_email);
			  $stmt->bindParam(":client_web", $client_web);
			  $stmt->bindParam(":client_vat_id", $client_vat_id);
			  $stmt->bindParam(":client_tax_code", $client_tax_code);
			  $stmt->bindParam(":client_active", $client_active);
		  
          $result = $stmt->execute();
		  if($result){
			 //selecting MAX ID to Map External System Client ID and Invoice Plane Client ID
			$sql = "SELECT MAX(client_id) FROM ip_clients ";
            $stmt = $conn->query($sql);
            $maxid = $stmt->fetchAll();
		    $this['logger']->info("Record Created For This ID".$maxid[0]['MAX(client_id)']);
			//Creating Record for Map External System Client ID and Invoice Plane Client ID
			$sql = "INSERT INTO ext_client_map(ext_client_id, client_id) VALUES(:ext_client_id, :client_id)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":ext_client_id", $ext_client_id);
            $stmt->bindParam(":client_id", $maxid[0]['MAX(client_id)']);
            $result = $stmt->execute();
		   }
          $data['status'] = $result;
		 }
        // Return the result
        $response = $response->withHeader('Content-Type','application/json');
        $response = $response->withStatus(200);
        $response = $response->withJson($data);
        return $response;
      } catch (PDOException $e) {
        $this['logger']->error("DataBase Error.<br/>" . $e->getMessage());
      } catch (Exception $e) {
        $this['logger']->error("General Error.<br/>" . $e->getMessage());
      } finally {
        // Destroy the database connection
        $conn = null;
      }
      });
	  
	
    $app->post('/families', function (Request $request, Response $response) {
      // Gets quote and user id
     
	        $ext_family_id=$request->getParam('ext_family_id');
			$family_name=$request->getParam('family_name');
            
           

      // Gets the database connection
      $conn = PDOConnection::getConnection();

      try {
        // Gets the user into the database
  			$sql = "SELECT * FROM ext_family_map where 	ext_family_id=:ext_family_id";
  			$stmt = $conn->prepare($sql);
  			$stmt->bindParam(":ext_family_id", $ext_family_id);
  			$stmt->execute();
  			$query = $stmt->fetchObject();

  			// If user exist
  			if ($query) {
                $data['status'] = "Families Aleady Exist";
        } else {
			
          // Insert Data for Client Who is not exist:
     
          $sql = "INSERT INTO  ip_families(family_name)VALUES(:family_name)";
			  $stmt = $conn->prepare($sql);
			  $stmt->bindParam(":family_name", $family_name);
			  
		  
          $result = $stmt->execute();
		  if($result){
			 //selecting MAX ID to Map External System Families ID and Invoice Plane families ID
			$sql = "SELECT MAX(family_id) FROM ip_families ";
            $stmt = $conn->query($sql);
            $maxid = $stmt->fetchAll();
		    $this['logger']->info("Record Created For This ID".$maxid[0]['MAX(family_id)']);
			//Creating Record for Map External System Client ID and Invoice Plane Client ID
			$sql = "INSERT INTO ext_family_map(ext_family_id, family_id) VALUES(:ext_family_id, :family_id)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":ext_family_id", $ext_family_id);
            $stmt->bindParam(":family_id", $maxid[0]['MAX(family_id)']);
            $result = $stmt->execute();
		   }
          $data['status'] = $result;
		 }
        // Return the result
        $response = $response->withHeader('Content-Type','application/json');
        $response = $response->withStatus(200);
        $response = $response->withJson($data);
        return $response;
      } catch (PDOException $e) {
        $this['logger']->error("DataBase Error.<br/>" . $e->getMessage());
      } catch (Exception $e) {
        $this['logger']->error("General Error.<br/>" . $e->getMessage());
      } finally {
        // Destroy the database connection
        $conn = null;
      }
      });
	  
	  /**
     * This method create  record for product 
     * @param string $quote - The text of post
     * @param int $id - The user id
     */
    $app->post('/products', function (Request $request, Response $response) {
      // Gets quote and user id
     
	        $ext_product_id=$request->getParam('ext_product_id');
			$this['logger']->info("EXT Product ID".$ext_product_id);
			$product_sku=$request->getParam('product_sku');
			$family_id=$request->getParam('family_id');//change required
			$product_name=$request->getParam('product_name');
			$product_description=$request->getParam('product_description');
			if($product_description === NULL){
				$product_description=" ";
			}
			$product_price=$request->getParam('product_price');
			$purchase_price=$request->getParam('purchase_price');
			$provider_name=$request->getParam('provider_name');
			$tax_rate_id=$request->getParam('tax_rate_id');//change required
			$unit_id=$request->getParam('unit_id');//change required
			$product_tariff=$request->getParam('product_tariff');
            
    

      // Gets the database connection
      $conn = PDOConnection::getConnection();

      try {
        // Gets the user into the database
  			$sql = "SELECT * FROM ext_product_map where ext_product_id=:ext_product_id";
  			$stmt = $conn->prepare($sql);
  			$stmt->bindParam(":ext_product_id", $ext_product_id);
  			$stmt->execute();
  			$query = $stmt->fetchObject();
  			// If user exist
  			if ($query) {
                $data['status'] = "Product Aleady Exist";
            } else {	
          // Insert Data for Client Who is not exist:
          $sql = "INSERT INTO   ip_products(product_sku,family_id,product_name,product_description,product_price,purchase_price,provider_name,tax_rate_id,unit_id,product_tariff)VALUES(:product_sku,:family_id,:product_name,:product_description,:product_price,:purchase_price,:provider_name,:tax_rate_id,:unit_id,:product_tariff)";
			  $stmt = $conn->prepare($sql);
			  $stmt->bindParam(":product_sku", $product_sku);
			  $family_id_ip=LookupData::getLookupValue("ext_family_map","family_id","ext_family_id",$family_id);
			  $stmt->bindParam(":family_id",  $family_id_ip);
			  $stmt->bindParam(":product_name", $product_name);
			  $stmt->bindParam(":product_description", $product_description);
			  $stmt->bindParam(":product_price", $product_price);
			  $stmt->bindParam(":purchase_price", $purchase_price);
			  $stmt->bindParam(":provider_name", $provider_name);
			  $tax_rate_id_ip=LookupData::getLookupValue("ext_tax_map","tax_id","ext_tax_id",$tax_rate_id);
		      $stmt->bindParam(":tax_rate_id", $tax_rate_id_ip);
			  $unit_id_ip=LookupData::getLookupValue("ext_unit_map","unit_id","ext_unit_id",$unit_id);
		      $stmt->bindParam(":unit_id", $unit_id_ip);
			  $stmt->bindParam(":product_tariff", $product_tariff);
			   
          $result = $stmt->execute();
		  if($result){
			 //selecting MAX ID to Map External System Families ID and Invoice Plane families ID
			$sql = "SELECT MAX(product_id) FROM ip_products ";
            $stmt = $conn->query($sql);
            $maxid = $stmt->fetchAll();
		    $this['logger']->info("Record Created For This ID".$maxid[0]['MAX(product_id)']);
			//Creating Record for Map External System Client ID and Invoice Plane Client ID
			$sql = "INSERT INTO ext_product_map(ext_product_id, product_id) VALUES(:ext_product_id, :product_id)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":ext_product_id", $ext_product_id);
            $stmt->bindParam(":product_id", $maxid[0]['MAX(product_id)']);
            $result = $stmt->execute();
		   }
          $data['status'] = $result;
		 }
        // Return the result
        $response = $response->withHeader('Content-Type','application/json');
        $response = $response->withStatus(200);
        $response = $response->withJson($data);
        return $response;
      } catch (PDOException $e) {
        $this['logger']->error("DataBase Error.<br/>" . $e->getMessage());
      } catch (Exception $e) {
        $this['logger']->error("General Error.<br/>" . $e->getMessage());
      } finally {
        // Destroy the database connection
        $conn = null;
      }
      });
	  //Taxs sync
	  // It will sync all tax rate
	  
	  $app->post('/taxrates', function (Request $request, Response $response) {
      // Gets quote and user id
     
	        $ext_tax_id=$request->getParam('ext_tax_id');
			$tax_rate_name=$request->getParam('tax_rate_name');
			$tax_rate_percent=$request->getParam('tax_rate_percent');
            
           

      // Gets the database connection
      $conn = PDOConnection::getConnection();

      try {
        // Gets the user into the database
  			$sql = "SELECT * FROM ext_tax_map where ext_tax_id=:ext_tax_id";
  			$stmt = $conn->prepare($sql);
  			$stmt->bindParam(":ext_tax_id", $ext_tax_id);
  			$stmt->execute();
  			$query = $stmt->fetchObject();

  			// If user exist
  			if ($query) {
                $data['status'] = "Tax Rate Aleady Exist";
        } else {
			
          // Insert Data for Client Who is not exist:
     
          $sql = "INSERT INTO  ip_tax_rates(tax_rate_name,tax_rate_percent)VALUES(:tax_rate_name,:tax_rate_percent)";
			  $stmt = $conn->prepare($sql);
			  $stmt->bindParam(":tax_rate_name", $tax_rate_name);
			  $stmt->bindParam(":tax_rate_percent", $tax_rate_percent);
          $result = $stmt->execute();
		  if($result){
			 //selecting MAX ID to Map External System Families ID and Invoice Plane families ID
			$sql = "SELECT MAX(tax_rate_id) FROM ip_tax_rates ";
            $stmt = $conn->query($sql);
            $maxid = $stmt->fetchAll();
		    $this['logger']->info("Record Created For This ID".$maxid[0]['MAX(tax_rate_id)']);
			//Creating Record for Map External System Client ID and Invoice Plane Client ID
			$sql = "INSERT INTO ext_tax_map(ext_tax_id, tax_id) VALUES(:ext_tax_id, :tax_id)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":ext_tax_id", $ext_tax_id);
            $stmt->bindParam(":tax_id", $maxid[0]['MAX(tax_rate_id)']);
            $result = $stmt->execute();
		   }
          $data['status'] = $result;
		 }
        // Return the result
        $response = $response->withHeader('Content-Type','application/json');
        $response = $response->withStatus(200);
        $response = $response->withJson($data);
        return $response;
      } catch (PDOException $e) {
        $this['logger']->error("DataBase Error.<br/>" . $e->getMessage());
      } catch (Exception $e) {
        $this['logger']->error("General Error.<br/>" . $e->getMessage());
      } finally {
        // Destroy the database connection
        $conn = null;
      }
      });
	  
	  
	  
	   //It will Sync Unit
	  // It will sync all tax rate
	  
	  $app->post('/units', function (Request $request, Response $response) {
      // Gets quote and user id
     
	        $ext_unit_id=$request->getParam('ext_unit_id');
			$unit_name=$request->getParam('unit_name');
		
           

      // Gets the database connection
      $conn = PDOConnection::getConnection();

      try {
        // Gets the user into the database
  			$sql = "SELECT * FROM ext_unit_map where ext_unit_id=:ext_unit_id";
  			$stmt = $conn->prepare($sql);
  			$stmt->bindParam(":ext_unit_id", $ext_unit_id);
  			$stmt->execute();
  			$query = $stmt->fetchObject();

  			// If user exist
  			if ($query) {
                $data['status'] = "Unit Aleady Exist";
        } else {
			
          // Insert Data for Client Who is not exist:
     
          $sql = "INSERT INTO  ip_units(unit_name)VALUES(:unit_name)";
			  $stmt = $conn->prepare($sql);
			  $stmt->bindParam(":unit_name", $unit_name);
          $result = $stmt->execute();
		  if($result){
			 //selecting MAX ID to Map External System Families ID and Invoice Plane families ID
			$sql = "SELECT MAX(unit_id) FROM ip_units ";
            $stmt = $conn->query($sql);
            $maxid = $stmt->fetchAll();
		    $this['logger']->info("Record Created For This ID".$maxid[0]['MAX(unit_id)']);
			//Creating Record for Map External System Client ID and Invoice Plane Client ID
			$sql = "INSERT INTO ext_unit_map(ext_unit_id, unit_id) VALUES(:ext_unit_id, :unit_id)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":ext_unit_id", $ext_unit_id);
            $stmt->bindParam(":unit_id", $maxid[0]['MAX(unit_id)']);
            $result = $stmt->execute();
		   }
          $data['status'] = $result;
		 }
        // Return the result
        $response = $response->withHeader('Content-Type','application/json');
        $response = $response->withStatus(200);
        $response = $response->withJson($data);
        return $response;
      } catch (PDOException $e) {
        $this['logger']->error("DataBase Error.<br/>" . $e->getMessage());
      } catch (Exception $e) {
        $this['logger']->error("General Error.<br/>" . $e->getMessage());
      } finally {
        // Destroy the database connection
        $conn = null;
      }
      });
	  
	  
	   /**
     * This method list the users for likes
     * @param int $id - quote id
     */
    $app->get('/invoices/{id}', function (Request $request, Response $response) {
      // Gets quote
      $id = $request->getAttribute('id');

      // Gets the database connection
      $conn = PDOConnection::getConnection();

      try {
        // Gets the posts into the database
        $sql = "SELECT ip_invoices.invoice_id,ip_invoices.user_id,ext_client_map.ext_client_id, ip_invoices.invoice_date_created, ip_invoices.invoice_time_created,	ip_invoices.invoice_number,ip_invoice_amounts.invoice_item_subtotal,ip_invoice_amounts.invoice_item_tax_total, ip_invoice_amounts.invoice_total  FROM ip_invoices , ip_invoice_amounts, ext_client_map WHERE ip_invoices.invoice_id =ip_invoice_amounts.invoice_id and ip_invoices.invoice_id=:id and  ext_client_map.client_id = ip_invoices.client_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $data = $stmt->fetchAll();
		
		$sqlItem = "select ip_invoice_items.item_quantity, ip_invoice_items.item_price,ip_invoice_items.item_price,  ip_invoice_items.item_discount_amount,  ext_product_map.ext_product_id, ext_tax_map.ext_tax_id
                   from ip_invoice_items , ext_product_map , ext_tax_map
                   where 
                   ip_invoice_items.invoice_id=:id
                  and  ext_product_map.product_id = ip_invoice_items.item_product_id
                  and  ext_tax_map.tax_id = ip_invoice_items.item_tax_rate_id";
        $stmtItem = $conn->prepare($sqlItem);
        $stmtItem->bindParam(':id', $id);
        $stmtItem->execute();
        $items = $stmtItem->fetchAll();
       
        // Return a list
        $response = $response->withHeader('Content-Type','application/json');
        $response = $response->withStatus(200);
        $response = $response->withJson(array(
		   
            "invoice_id"=> $data[0]['invoice_id'],
            "user_id" => $data[0]['user_id'],
            "ext_client_id" => $data[0]['ext_client_id'],
            "invoice_date_created" => $data[0]['invoice_date_created'],
			"invoice_time_created"=>$data[0]['invoice_time_created'],
			"invoice_item_subtotal"=>$data[0]['invoice_item_subtotal'],
			"invoice_item_tax_total"=>$data[0]['invoice_item_tax_total'],
			"invoice_total"=>$data[0]['invoice_total'],
            "invoice_number"=>$data[0]['invoice_number'],
            "items" => $items
         ));
		//$this['logger']->info($data);
        return $response;
      } catch (PDOException $e) {
        $this['logger']->error("DataBase Error.<br/>" . $e->getMessage());
      } catch (Exception $e) {
        $this['logger']->error("General Error.<br/>" . $e->getMessage());
      } finally {
        // Destroy the database connection
        $conn = null;
      }
    });
	
	 $app->get('/payments/{id}', function (Request $request, Response $response) {
      // Gets quote
      $id = $request->getAttribute('id');
      // Gets the database connection
      $conn = PDOConnection::getConnection();
      try {
        // Gets the posts into the database
        $sql = "Select ip_payments.payment_id, ip_payments.invoice_id ,ip_payments.payment_date, ip_payments.payment_amount, ip_invoices.invoice_number  from ip_payments , ip_invoices where ip_payments.payment_id=:id and  ip_payments.invoice_id  = ip_invoices.invoice_id;";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $data = $stmt->fetchAll();
        // Return a list
        $response = $response->withHeader('Content-Type','application/json');
        $response = $response->withStatus(200);
        $response = $response->withJson($data);
        return $response;
      } catch (PDOException $e) {
        $this['logger']->error("DataBase Error.<br/>" . $e->getMessage());
      } catch (Exception $e) {
        $this['logger']->error("General Error.<br/>" . $e->getMessage());
      } finally {
        // Destroy the database connection
        $conn = null;
      }
    });

  });
 
?>
