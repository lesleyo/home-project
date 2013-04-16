<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Super P Bear Page</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="css/init.css" rel="stylesheet">

  </head>

  <body>
	<div class="container">
		<div class="header">
			<div class="content">
				<h1>Dr P Bear</h1> 
				<h2>The date is <?php echo date("d-M-Y h:m:s") ?></h2>
				<h2>Can I connect to MySQL?
					<?php
					// Create connection
					$con=mysqli_connect("localhost","root","sweetpotato","mydatabase");

					// Check connection
					if (mysqli_connect_errno($con))
					{?>
						<span style="color:red;">NO :( <?php echo mysqli_connect_error() ?></span><?php
					}
					else
					{?>
						<span style="color:green;">YES :)</span><?php
					}
					?>
				</h2>
			</div>
		</div>
		<div class="navigation">
			<div class="content">
				<ul>
					<li><a href="#">Home</a></li>
					<li><a href="#">About</a></li>
					<li><a href="#">Secret chocolate secrets</a></li>
					<li><a href="#">Secret stashes</a></li>
					<li><a href="#">Recipes</a></li>
					<li><a href="#">How to get humans to bring you chocolate</a></li>
				</ul>
			</div>
		</div>
		<div class="main">
			<div class="content">
				<div class="span5">
					<img src="img/gallery/headshot1.jpg" width="400" height="400">			
				</div>
				<div class="span5">
					<img src="img/gallery/headshot2.jpg" width="400" height="400">			
				</div>
			</div>
		</div>
		<div class="footer">
			<div class="content">
				gfsdegsdrgsd
			</div>
		</div>
	</div>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
  </body>
</html> 