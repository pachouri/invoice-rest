<?php

  // Load configurations
  require "config/config.php";
  // Load authentication
  require "libs/auth.php";
  // Load database connection
  require "libs/connection.php";
  //Load Custom Function
  require "libs/lookup.php";
  // Load api
  require "routes/api.php";

?>
