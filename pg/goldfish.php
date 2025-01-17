<?php
/*
	goldfish - the PHP auto responder for postfix

    Copyright © 2007-2015 - Authors:
    
    (c) 2007-2009 Remo Fritzsche    (Main application programmer)
    (c) 2009 Karl Herrick (Bugfix)
    (c) 2007-2008 Manuel Aller (Additional programming)
    (c) 2015 Dirk Groenen (Additional programming)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
    Version 1.1
*/
	
    ini_set('display_errors', true);
    error_reporting( E_ALL );
    
    ######################################
    # Check PHP version #
    ######################################
    
	if ( version_compare( PHP_VERSION, "5.0.0" ) == - 1 )
	{
		echo "Error, you are currently not running PHP 5 or later. Exiting.\n";
		exit;
	}
    
    ######################################
    # Configuration #
    ######################################
    /* General */
    $conf['cycle'] = 5 * 60;
    
    /* Logging */
    $conf['log_file_path'] = "/var/log/goldfish/goldfish.log";
    $conf['write_log'] = true;
    
    /* Database information */
    $conf['pg_host'] = "localhost";
    $conf['pg_user'] = "postfix";
    $conf['pg_password'] = "mW5IurHZnnpV";
    $conf['pg_database'] = "postfix";
    $conf['pg_port'] = 5432;
    
    /* Database Queries */
    
    # This query has to return the path (`path`) of the corresponding maildir-Mailbox with email-address %m
    #$conf['q_mailbox_path'] = "SELECT CONCAT('/var/vmail/', maildir) AS path FROM mailbox WHERE username = '%m'";
    
    # This query has to return the following fields from the autoresponder table: `from_date`, `to_date`, `email`, `message` where `enabled` = true
    #$conf['q_forwardings'] = "SELECT * FROM autoresponder WHERE enabled = true AND force_disabled = false"; //modified for the autoresponder plugin
    $conf['q_forwardings'] = "SELECT autoresponder.email, autoresponder.descname, CONCAT('/var/vmail/', mailbox.maildir) as path
      FROM autoresponder
      INNER JOIN mailbox ON autoresponder.email=mailbox.username
      WHERE autoresponder.from_date <= CURRENT_DATE AND (autoresponder.to_date >= CURRENT_DATE OR autoresponder.to_date IS NULL) AND autoresponder.force_disabled = false;";
    
    # This query has to disable every autoresponder entry which ended in the past
    #$conf['q_disable_forwarding'] = "UPDATE autoresponder SET enabled = false WHERE to_date < CURRENT_DATE;";
    
    # This query has to activate every autoresponder entry which starts today
    #$conf['q_enable_forwarding'] = "UPDATE autoresponder SET enabled = true WHERE from_date <= CURRENT_DATE AND (to_date >= CURRENT_DATE OR to_date IS NULL);"; //modified for the autoresponder plugin
    
    # This query has to return the message of an autoresponder entry identified by email %m
    #$conf['q_messages'] = "SELECT message FROM autoresponder WHERE email = '%m';";
    
    # This query has to return the subject of the autoresponder entry identified by email %m
    #$conf['q_subject'] = "SELECT subject FROM autoresponder WHERE email = '%m';";

    # This query has to return the subject and message of an autoresponder entry identified by email %m
    $conf['q_data'] = "SELECT subject, message FROM autoresponder WHERE email = '%m';";
    

    ############################################################################
    #
    # Don't edit anything below here unless you know what you're doing!
    #
    ############################################################################

    ######################################
    # Logger class #
    ######################################
    
    class Logger
    {
		var $logfile;
		var $str;
		function addLine($str)
		{
		    $str = date("Y-m-d h:i:s")." ".$str;
		    $this->str .= "\n$str";
		    echo $str."\n";
		}
		
		function writeLog(&$conf)
		{
		    if (! $conf['write_log'] ) return;
		    
		    if (is_writable($conf['log_file_path']))
		    {
		    	$this->addLine("--------- End execution ------------");
	   	    	if (!$handle = fopen($conf['log_file_path'], 'a'))
	   	    	{
	                echo "Cannot open file ({$conf['log_file_path']})";
	                exit;
	            }
	
	            if (fwrite($handle, $this->str) === FALSE)
	            {
	                echo "Cannot write to file)";
	                exit;
	            }
	            else
	            {
					echo "Wrote log successfully.";
		    	}
	
	            fclose($handle);
	
		  }
		  else
		  {
			echo "Error: The log file is not writeable.\n";
			echo "The log has not been written.\n";
		  }
		}
    }
    
    ######################################
    # Create log object #
    ######################################
    $log = new Logger();
    
    ######################################
    # function endup() #
    ######################################
    function endup(&$log, &$conf)
    {
		$log->writeLog($conf);
		exit;
    }
    
    ######################################
    # Database connection #
    ######################################
    $db = @pg_connect("host={$conf['pg_host']} port={$conf['pg_port']} dbname={$conf['pg_database']} user={$conf['pg_user']} password={$conf['pg_password']}");
    if (!$db)
    {
                $log->addLine("Could not connect to database. Aborting.");
                endup($log, $conf);
    }
    else
    {
        $log->addLine("Connection to database {$conf['pg_database']} established successfully");
    }
    
    ######################################
    # Update database entries #
    ######################################
    # I'm removing enabled column from the db and using from_date/to_date in where clause of
    # forwarding query.  This save unnecessary writes to the database.
    /*
    $result = pg_query($db, $conf['q_disable_forwarding']);
    
    if (!$result)
    {
		$log->addLine("Error in query ".$conf['q_disable_forwarding']."\n");
    }
    else
    {
		$log->addLine("Successfully updated database (disabled entries)");
    }
    
    pg_query($db, $conf['q_enable_forwarding']);
    
    if (!$result)
    {
		$log->addLine("Error in query ".$conf['q_enable_forwarding']."\n");
    }
    else
    {
		$log->addLine("Successfully updated database (enabled entries)");
    }
    */
    
    ######################################
    # Catching dirs of autoresponders mailboxes #
    ######################################
    # FIXME:  We don't get any error result strings when using pg_query.
    #         We would have to use pg_send_query() in conjunction with pg_result_error()
    #         to get pg errors.

    // Corresponding email addresses
    $result = pg_query($db, $conf['q_forwardings']);
    
    if (!$result)
    {
    	$log->addLine("Error in query ".$conf['q_forwardings']."\n");
    	exit;
    }

    while ($row = pg_fetch_assoc($result)) {
      $emails[] = $row['email'];
      $name[] = $row['descname'];
      $paths[] = $row['path'] . 'new/';
    }
   
    $num = pg_num_rows($result);

    ######################################
    # Reading new mails #
    ######################################
    if ($num > 0)
    {
        $log->addLine("Reading new emails: new emails found: " . $num);
	    $i = 0;
	    
	    foreach ($paths as $path)
	    {

	    	foreach(scandir($path) as $entry)
	    	{
                        $log->addLine("Start scanning directory " . $path);
                        # intialize subject and message to NULL on initial search of path.  Subject and message will get cached for
                        # each path if new emails are present.
                        $subject = NULL;
                        $message = NULL;

		    	if ($entry != '.' && $entry != '..')
		    	{
                    $log->addLine("Found entry [" . $entry . "] in directory " . $path);
                    
					if (time() - filemtime($path . $entry) - $conf['cycle'] <= 0)
					{
			    		$mails[] = $path . $entry;
			    		
					    ###################################
					    # Send response #
					    ###################################
			    
			    		// Reading mail address
			   			$mail = file($path.$entry);
			    		
    					foreach ($mail as $line)
    					{
        					$line = trim($line);
            					
    						if (substr($line, 0, 12) == 'Return-Path:')
        					{
            					$returnpath = substr($line, strpos($line, '<') + 1, strpos($line, '>') - strpos($line, '<')-1)."\n";
        					} 
    					
    						if (substr($line, 0, 5) == 'From:' && strstr($line,"@"))
					        {
						        $address = substr($line, strpos($line, '<') + 1, strpos($line, '>') - strpos($line, '<')-1)."\n";
						        break;
					        } 
					        elseif(substr($line,0,5) == 'From:' && !strstr($line,"@") && !empty ($returnpath))
					        {
				                $address = $returnpath;
				                break;
					        } 
    					} 
		    
			    		// Check: Is this mail allready answered
			    
			    		if (empty($address))
			    		{
							$log->addLine("Error, could not parse mail $path");
			    		}
			    		else
			    		{
							// Get data of current mail
				   			$email = $emails[$i];

                # Only retrieve subject and message from db if they haven't been cached yet
                if ( $subject === NULL || $message === NULL) {
                  $result = pg_query($db, str_replace("%m", $emails[$i], $conf['q_data']));
                
                  if (!$result)
                  {
                    $log->addLine("Error in query ".$conf['q_data']."\n");
                    exit;
                  }
                  $row = pg_fetch_assoc($result);
                  $subject = $row['subject'];
                  $message = $row['message'];
                  $log->addLine("Caching message, email = ".$email." subject = ".$subject."\n");
                }

				    		$headers = "From: ".$name[$i]."<".$emails[$i].">\r\n";
                                                $headers .= "MIME-Version: 1.0\r\n";
                                                $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";

	
				    		// Check if mail is allready an answer:
				    		if (strstr($mail, $message))
				    		{
								$log->addLine("Mail from {$emails[$i]} allready answered");
								break;
				    		}
				
							// strip the line break from $address for checks
							// fix by Karl Herrick, thank's a lot
							if ( substr($address,0,strlen($address)-1) == $email )
							{
							        $log->addLine("Email address from autoresponder table is the same as the intended recipient! Not sending the mail!");
							        break;
							}

							$sent = mail($address, $subject, $message, $headers);

                            if($sent){
                                $log->addLine("Autoresponse e-mail was sent to: " . $address);
                            }
                            else{
                                $log->addLine("Autoresponse was not sent. Something went wrong");   
                            }
			   			}
					}
		    	}
			}
			
			$i++;
		}
	}
    else
    {
        $log->addLine("No new email found. Doing nothing...");
    }
	$log->writeLog($conf);
	echo "End execution."; 
?>
