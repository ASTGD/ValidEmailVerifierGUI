<HTML>

<HEAD>

<TITLE>Valid Email Verifier</TITLE>

</HEAD>

<BODY>


<?php
	require("email_validation.php");

	$validator=new email_validation_class;

	/*
	 * If you are running under Windows or any other platform that does not
	 * have enabled the MX resolution function GetMXRR() , you need to
	 * include code that emulates that function so the class knows which
	 * SMTP server it should connect to verify if the specified address is
	 * valid.
	 */
	if(!function_exists("GetMXRR"))
	{
		/*
		 * If possible specify in this array the address of at least on local
		 * DNS that may be queried from your network.
		 */
		$_NAMESERVERS=array();
		include("getmxrr.php");
	}
	/*
	 * If GetMXRR function is available but it is not functional, you may
	 * use a replacement function.
	 */
	/*
	else
	{
		$_NAMESERVERS=array();
		if(count($_NAMESERVERS)==0)
			Unset($_NAMESERVERS);
		include("rrcompat.php");
		$validator->getmxrr="_getmxrr";
	}
	*/

	/* how many seconds to wait before each attempt to connect to the
	   destination e-mail server */
	$validator->timeout=10;

	/* how many seconds to wait for data exchanged with the server.
	   set to a non zero value if the data timeout will be different
		 than the connection timeout. */
	$validator->data_timeout=0;

	/* user part of the e-mail address of the sending user
	   (info@phpclasses.org in this example) */
	$validator->localuser="email-verify";

	/* domain part of the e-mail address of the sending user */
	$validator->localhost="topvev.com";

	/* Set to 1 if you want to output of the dialog with the
	   destination mail server */
	$validator->debug=1;

	/* Set to 1 if you want the debug output to be formatted to be
	displayed properly in a HTML page. */
	$validator->html_debug=1;


	/* When it is not possible to resolve the e-mail address of
	   destination server (MX record) eventually because the domain is
	   invalid, this class tries to resolve the domain address (A
	   record). If it fails, usually the resolver library assumes that
	   could be because the specified domain is just the subdomain
	   part. So, it appends the local default domain and tries to
	   resolve the resulting domain. It may happen that the local DNS
	   has an * for the A record, so any sub-domain is resolved to some
	   local IP address. This  prevents the class from figuring if the
	   specified e-mail address domain is valid. To avoid this problem,
	   just specify in this variable the local address that the
	   resolver library would return with gethostbyname() function for
	   invalid global domains that would be confused with valid local
	   domains. Here it can be either the domain name or its IP address. */
	$validator->exclude_address="";

	if(IsSet($_GET["email"]))
		$email=$_GET["email"];
	if(IsSet($email)
	&& strcmp($email,""))
	{
		if(($result=$validator->ValidateEmailBox($email))<0)
			echo "Result-TestUnsuccessful\n";
		else
			echo "Result-".($result ? "" : "not")."valid.\n";
	}
	else
	{
		echo "<H2>TestAborted</H2>\n";
	}
?>
<HR>
</BODY>
</HTML>
