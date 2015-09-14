# sparkpay-signifyd
Signifyd Integration for SparkPay

This integration requires some initial configuration. <br>
This also requires that the program be hosted on an external host somewhere (such as HostGator or GoDaddy).

#Configuration
###Connecting to your API:
Integration requries 3 peices of configuration: <br>
`$sig_key`   -- Your Signifyd API Key <br>
`$spark_key` -- Your SparkPay API Key (private-use oAuth Token) <br>
`$spark_url` -- The full URL to your SparkPay Store API <br>

You should edit these values directly in the `signifyd_sparkpay.php` source file (lines 21-25)
```
private $sig_key   = 'your_signifyd_api_key';                       //your signifyd key
private $sig_url   = 'https://api.signifyd.com/v2/cases';           //do not edit -- signifyd api url
private $spark_key = 'your_sparkpay_key';                           //your sparkpay api key
private $spark_url = 'https://www.your_site.com/api/v1/';           //your store api url
```

###Uploading the script to an external server
Note that because SparkPay does not support the execution of server side code, this file *must* be uploaded and hosted on an external server. Shared hosting from GoDaddy or the sort will work just fine, and is generally under $10 a month.

Once this script is uploaded to the web, make a note of its URL. We will need it for the frontend javascript.

###Implementing the FrontEnd 'Trigger' Script
Upon the placement of a successful purchase, frontend JavaScript will connect to and 'trigger' this integration file. <br>
The JavaScript should simply make a GET request to the integration script with the order id sent as a parameter for `id`. <br>
<br>
The frontend script should be placed at the bottom of the `OrderView` template file (before the last closing tag).<br>
The `beaconUrl` is the URL to your external host. It MUST use HTTPS:
<br><br>**Example Script**:
```
<!--Signifyd Script-->
  <script type="text/javascript">
    function getParameterByName(name) {
      name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
      var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
          results = regex.exec(location.search);
      return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
    }
    (function() {
      var orderId=getParameterByName('orderID'),
          beaconUrl = 'https://your_external_host_domain_here/signifyd.php?id='+orderId,
          img = document.createElement('img');
      img.src= beaconUrl;
      img.style.width = '1px';
      img.style.height = '1px';
      document.body.appendChild(img);
    })();
  </script>
```

