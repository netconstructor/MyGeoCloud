<?php
include("html_header.php");
?>

    <div class="navbar navbar-fixed-top">
      <div class="navbar-inner">
        <div class="container">
          <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </a>
          <a class="brand" href="#">World Trail Map</a>
          <div class="nav-collapse">
            <ul class="nav">
              <li class="active"><a href="#">Home</a></li>
              <li><a href="#about">About</a></li>
              <li><a href="#contact">Contact</a></li>
            </ul>
          </div><!--/.nav-collapse -->
        </div>
      </div>
    </div>

    <div class="container">

      <!-- Main hero unit for a primary marketing message or call to action -->
      <div class="hero-unit" style="height:500px;padding:0px">
        <iframe frameborder="no" width="100%" height="100%" src="http://test.mygeocloud.com/apps/viewer/openlayers/<?php echo $postgisdb;?>?layers=public.ways"></iframe>
      </div>

      <!-- Example row of columns -->
      <div class="row">
        <div class="span6">
          <h2>Heading</h2>
           <p>Donec id elit non mi porta gravida at eget metus. Fusce 
dapibus, tellus ac cursus commodo, tortor mauris condimentum nibh, ut 
fermentum massa justo sit amet risus. Etiam porta sem malesuada magna 
mollis euismod. Donec sed odio dui. </p>
          <p><a class="btn" href="#">View details »</a></p>
        </div>
        <div class="span6">
          <h2>Heading</h2>
           <p>Donec id elit non mi porta gravida at eget metus. Fusce 
dapibus, tellus ac cursus commodo, tortor mauris condimentum nibh, ut 
fermentum massa justo sit amet risus. Etiam porta sem malesuada magna 
mollis euismod. Donec sed odio dui. </p>
          <p><a class="btn" href="#">View details »</a></p>
       </div>
      </div>
<?php
include("html_footer.php");
      