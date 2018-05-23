<?php
   include ("file1.php");

   class ClassB
   {

     function __construct()
     {
     }

     function callA()
     {
       $classA = new ClassA();
       $name = $classA->getName();
       echo $name;    //Prints John
     }
   }

   $classb = new ClassB();
   $classb->callA();
?>