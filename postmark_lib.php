<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once("Postmark/Postmark_Autoloader.php");

define("SPAM_STATUS_YES", "Yes");
define("SPAM_STATUS_NO", "No");
define("SPAM_THRESHOLD", 10); //anything over 5 is spam but the spam checker is rubbish!
define("SPF_PASS", "pass");
define("SPF_NEUTRAL", "neutral");
define("SPF_FAIL", "fail");

class Postmark_lib
{  
  function Postmark_lib()            
  {        
      $this->ci =& get_instance();
  }
  
  function inbound()
  {
    $inbound = new Postmark_Inbound(file_get_contents('php://input'));
    return $this->parse_data($inbound);
  }
  
  function test()
  {
    $inbound = new Postmark_Inbound($this->_test_reply());
    return $this->parse_data($inbound);
  }
  
  function parse_data($inbound)
  {
    $this->ci->load->library('encrypt');
    $this->ci->load->library('message_lib');
    $this->ci->load->model('admin_user_db');
    
    if ($this->_is_spam($inbound)) 
    {
      log_message('error', "Spam ignored for message: ".$inbound->Subject());
      return;
    }
    
    //check the incoming customer details
    $email = $inbound->FromEmail();
    $name = explode(" ", $inbound->FromName());
    $last_name = array_pop($name);
    $first_name = implode($name);
    $subject = $inbound->Subject();
    $body = preg_replace('/\nOn (.*) wrote:\n/i', "", preg_replace('#(^\w.+:\n)?(^>.*(\n|$))+#mi', "", $inbound->TextBody())); //strip out reply data from original email
    
    //we need to assign an admin user based on the incoming TO email address
    $incoming_email = $inbound->ToEmail();
    $admin_user_id = 0;
    if ($this->ci->admin_user_db->get_where(array('enabled' => 1, 'email' => $incoming_email)))
    {
      $admin_user_id = $this->ci->admin_user_db->admin_user_id;
    }
    
    //does the message thread already exist?
    $matches = array();
    if (preg_match("/\[#([A-Za-z0-9~\/\.\-]+)\]/", str_replace(array(" ", "\\"), "", $subject), $matches))
    {
      $message_ids = explode("/", $matches[1]);
      $message_id = (int)$message_ids[0];
      if ($message_id == $this->ci->encrypt->decode($message_ids[1], ENCRYPTION_KEY))
      {
        $message_item_id = $this->ci->message_lib->add_response($message_id, 0, $body, TRUE, TRUE);
      }
      else 
      {
        $message_item_id = $this->ci->message_lib->add($first_name, $last_name, "", $email, $body, MESSAGE_TYPE_CUSTOMERSERVICE, 0, 0, $subject, $admin_user_id, FALSE); 
      }
    }
    else
    {
      $message_item_id = $this->ci->message_lib->add($first_name, $last_name, "", $email, $body, MESSAGE_TYPE_CUSTOMERSERVICE, 0, 0, $subject, $admin_user_id, FALSE);
    }
    
    $this->_get_attachments($inbound, $message_item_id);
  }
  
  private function _get_attachments($inbound, $message_item_id)
  {
    //manage attachments
    if ($inbound->HasAttachments())
    {
      $attachmentsObject = $inbound->Attachments();
      while ($attachmentsObject->valid())
      {
        $attachmentFile = $attachmentsObject->current();
        $upload_name = $this->ci->message_lib->santise_filename($attachmentFile->Name);
        $file_location = $this->ci->message_lib->put_file_record($message_item_id, $upload_name);
        $attachmentFile->Download($file_location);
        $attachmentsObject->next();
      }
    }
  }
  
  private function _is_spam($inbound)
  {
    //spam detection is a little awful
    //the spam score and spam yes/no isn't reliable - it's marked a lot of my
    //gMail incomming messages as spam
    //in an ideal world I wouldn't have had to comment out anything.
    if (
      strpos(strtolower($inbound->Subject()), strtolower("Undelivered Mail")) !== FALSE ||
      strpos(strtolower($inbound->Subject()), strtolower("Undeliverable")) !== FALSE ||
      strpos(strtolower($inbound->Subject()), strtolower("Delivery Status Notification")) !== FALSE ||
      strpos(strtolower($inbound->Subject()), strtolower("Automatic reply")) !== FALSE ||
      strpos(strtolower($inbound->Subject()), strtolower("Auto-response")) !== FALSE ||
      strpos(strtolower($inbound->Subject()), strtolower("Automatische Antwort")) !== FALSE ||
      strpos(strtolower($inbound->Subject()), strtolower("Auto-Reply")) !== FALSE ||
      strpos(strtolower($inbound->FromEmail()), strtolower("postmaster")) !== FALSE ||
      strpos(strtolower($inbound->FromEmail()), strtolower("MAILER-DAEMON")) !== FALSE
    )
    {
      return true;
    }
    if 
    (
      //$inbound->Headers("X-Spam-Status") == SPAM_STATUS_YES ||
      $inbound->Headers("X-Spam-Score") >= SPAM_THRESHOLD
    )
    {
      return true;
    }
    if (strtolower($inbound->Headers("Received-SPF")) == SPF_FAIL)
    {
      return true;
    }

    return false;      
  }
  
  function _test()
  {
    return '{
  "From": "lesley@goodprint.co.uk",
  "FromFull": {
    "Email": "lesley@goodprint.co.uk",
    "Name": "Lesley Oakey"
  },
  "To": "customerservice@smileprint.com",
  "ToFull": [
    {
      "Email": "customerservice@smileprint.com",
      "Name": ""
    }
  ],
  "Cc": "",
  "CcFull": [],
  "ReplyTo": "",
  "Subject": "Fwd: Test email to Goodprint Postmark Server",
  "MessageID": "d12d695d-e08f-465f-962e-50b5c983d484",
  "Date": "Wed, 3 Apr 2013 16:22:16 +0100",
  "MailboxHash": "",
  "TextBody": "Testing Postmark D:\n\n\n*Lesley Oakey*\nSenior Web Developer\nGoodprint UK Limited\n(01842) 770661\nwww.goodprint.co.uk\n",
  "HtmlBody": "<div dir=\"ltr\"><span style=\"color:rgb(80,0,80)\">Testing Postmark D:<\/span><br><div class=\"gmail_quote\"><div dir=\"ltr\"><div><div class=\"h5\"><div class=\"gmail_quote\"><div dir=\"ltr\"><div><br><div class=\"gmail_quote\"><div dir=\"ltr\">\n<span><font color=\"#888888\"><div><br><\/div><div><font face=\"arial, helvetica, sans-serif\" color=\"#333333\"><b>Lesley Oakey<\/b><\/font><\/div>\n\n<div><font face=\"arial, helvetica, sans-serif\" color=\"#333333\">Senior Web Developer\u00a0<\/font><\/div>\n<div><font face=\"arial, helvetica, sans-serif\" color=\"#333333\">Goodprint UK Limited<br>(01842)\u00a0770661<br><a href=\"http:\/\/www.goodprint.co.uk\" target=\"_blank\">www.goodprint.co.uk<\/a><\/font><br><\/div><\/font><\/span><\/div><\/div>\n<\/div><\/div><\/div><\/div><\/div><\/div><\/div>\n<\/div>\n",
  "Tag": "",
  "Headers": [
    {
      "Name": "X-Spam-Checker-Version",
      "Value": "SpamAssassin 3.3.1 (2010-03-16) onrs-ord-pm-inbound1.wildbit.com"
    },
    {
      "Name": "X-Spam-Status",
      "Value": "No"
    },
    {
      "Name": "X-Spam-Score",
      "Value": "-0.8"
    },
    {
      "Name": "X-Spam-Tests",
      "Value": "DKIM_SIGNED,DKIM_VALID,DKIM_VALID_AU,FREEMAIL_FROM,HTML_MESSAGE,RCVD_IN_DNSWL_LOW,SPF_PASS"
    },
    {
      "Name": "Received-SPF",
      "Value": "Pass (sender SPF authorized) identity=mailfrom; client-ip=209.85.216.48; helo=mail-qa0-f48.google.com; envelope-from=lesley.oakey@gmail.com; receiver=f4168da7d9408ae84a456ae3e94c8d97@inbound.postmarkapp.com"
    },
    {
      "Name": "DKIM-Signature",
      "Value": "v=1; a=rsa-sha256; c=relaxed\/relaxed;        d=gmail.com; s=20120113;        h=mime-version:x-received:in-reply-to:references:date:message-id         :subject:from:to:content-type;        bh=i3G\/X66ZY4x3ODtypoNm+\/W530yFtxeJRCN915IB\/k0=;        b=QWOCIqLu1rmTCQKNhFJPKd1W8LEYNULOSCKnXAlQKv++aKsgUGTBcgy7YEy0ScyNoc         Cq9gZf1icbVmp95+LsWzXxa2x3W7zymLNQA+GQ1P5x0AHbAJ32utzLhdDDGblU8alGuk         IpT4dJUS4r20tgdEMaa8Bsy6+cMiolX2L3\/9ymq6qvnDuXPW0tg6ve8zdAv6GFEpKxhT         xBMz8zGA3Fpx0L08ALG3H3ATITSk9n\/ry0afqRAsDQ0DHKCDo2eOA+V2JWxZOyTNNA0c         JSdfBijz3Mj+3GoYNhYyGytH15K2bFQf2bhfSeDkWCciwo5JzS1z9SsfvevcEAn8eJuZ         mwXQ=="
    },
    {
      "Name": "MIME-Version",
      "Value": "1.0"
    },
    {
      "Name": "X-Received",
      "Value": "by 10.229.56.90 with SMTP id x26mr690181qcg.88.1365002536791; Wed, 03 Apr 2013 08:22:16 -0700 (PDT)"
    },
    {
      "Name": "In-Reply-To",
      "Value": "<CAGGx_ja5pkDJmBr7BnDq4JiP_cc9gH=JYAHFna8v-0bq-n-qEQ@mail.gmail.com>"
    },
    {
      "Name": "References",
      "Value": "<CAGGx_jZMOm=Y0kXouLn2d402kD93WVn+OthHJvQFip+TpHWcRg@mail.gmail.com><CAGGx_jbW_8rDuntHF2Vqxfv2rQHAPtusXU=DLtfkK9NxdzkeEQ@mail.gmail.com><CAGGx_ja5pkDJmBr7BnDq4JiP_cc9gH=JYAHFna8v-0bq-n-qEQ@mail.gmail.com>"
    },
    {
      "Name": "Message-ID",
      "Value": "<CAGGx_jZgTZV42TniwCHPxZeL+gZXCT4miC65ce99M5m5QBSfug@mail.gmail.com>"
    }
  ],
  "Attachments": []
}';
    
  }
  
  
  function _test_reply()
  {
    return '{
  "From": "lesley.oakey@gmail.com",
  "FromFull": {
    "Email": "lesley.oakey@gmail.com",
    "Name": "Lesley Oakey"
  },
  "To": "f4168da7d9408ae84a456ae3e94c8d97@inbound.postmarkapp.com",
  "ToFull": [
    {
      "Email": "f4168da7d9408ae84a456ae3e94c8d97@inbound.postmarkapp.com",
      "Name": ""
    }
  ],
  "Cc": "",
  "CcFull": [],
  "ReplyTo": "",
  "Subject": "Re: Your support ticket [#174091/AGxWbFBjUTQEOwU3] has been replied to",
  "MessageID": "e01d0cf9-f95e-4e20-8a06-54e5e34f76d9",
  "Date": "Thu, 4 Apr 2013 10:40:54 +0100",
  "MailboxHash": "",
  "TextBody": "This for getting back to me. Please ignore all the attachment stuff\n\nLesley\n\n\nOn 4 April 2013 10:35, Lesley Oakey <lesley.oakey@gmail.com> wrote:\n\n> <http:\/\/localwin.goodprint.co.uk\/>\n> Thank you for your request.\n>\n>\n> Artwork can be purchased here <http:\/\/bit.ly\/4hlvZo>\n>\n>\n> Please ensure you include your order number and let us know which file\n> format you require and which parts of the artwork ie: logo only, logo +\n> company name, whole card etc...\n>\n>\n> You can respond to this ticket on our website\n> http:\/\/localwin.goodprint.co.uk\/contact\/respond\/174091\/AGxWbFBjUTQEOwU3\/ or\n> by replying to this email.\n>\n> How are we doing? We take customer service very seriously, and we\'d love\n> you to tell us how we\'re doing. All our feedback is read by the management\n> team, you can leave yours here http:\/\/www.goodprint.co.uk\/feedback\/show\/\n>\n> Kind Regards,\n> Customer Service\n> Goodprint UK Ltd\n> http:\/\/localwin.goodprint.co.uk\n>\n> Follow us online for exclusive discounts, offers and news:\n> Twitter <http:\/\/twitter.com\/goodprint> | Facebook<http:\/\/www.facebook.com\/pages\/Goodprint-UK-Ltd\/34379572152>\n>  | Blog <http:\/\/localwin.goodprint.co.uk\/blog\/>\n>\n\n\n\n-- \n*Lesley Oakey*\nSenior Web Developer\nGoodprint UK Limited\n(01842) 770661\nwww.goodprint.co.uk\n",
  "HtmlBody": "<div dir=\"ltr\">This for getting back to me. Please ignore all the attachment stuff<div><br><\/div><div>Lesley<br><div class=\"gmail_extra\"><br><br><div class=\"gmail_quote\">On 4 April 2013 10:35, Lesley Oakey <span dir=\"ltr\">&lt;<a href=\"mailto:lesley.oakey@gmail.com\" target=\"_blank\">lesley.oakey@gmail.com<\/a>&gt;<\/span> wrote:<br>\n\n<blockquote class=\"gmail_quote\" style=\"margin:0 0 0 .8ex;border-left:1px #ccc solid;padding-left:1ex\"><div dir=\"ltr\"><div><a href=\"http:\/\/localwin.goodprint.co.uk\/\" style=\"font-family:&#39;Times New Roman&#39;;font-size:medium\" target=\"_blank\"><img src=\"http:\/\/www.goodprint.co.uk\/img2\/includes\/goodprint_logo_164.gif\" width=\"164\" height=\"32\" style=\"border:0px;margin-bottom:10px\"><\/a><span style=\"font-size:medium;font-family:&#39;Times New Roman&#39;\"><\/span><div style=\"font-family:Arial;color:rgb(128,128,128);font-size:11px\">\n\n\nThank you for your request.<br><br><br>Artwork can be purchased\u00a0<a href=\"http:\/\/bit.ly\/4hlvZo\" target=\"_blank\">here<\/a><br><br><br>Please ensure you include your order number and let us know which file format you require and which parts of the artwork ie: logo only, logo + company name, whole card etc...<br>\n\n\n<br><br>You can respond to this ticket on our website\u00a0<a href=\"http:\/\/localwin.goodprint.co.uk\/contact\/respond\/174091\/AGxWbFBjUTQEOwU3\/\" target=\"_blank\">http:\/\/localwin.goodprint.co.uk\/contact\/respond\/174091\/AGxWbFBjUTQEOwU3\/<\/a>\u00a0or by replying to this email.<br>\n\n\n<br>How are we doing? We take customer service very seriously, and we&#39;d love you to tell us how we&#39;re doing. All our feedback is read by the management team, you can leave yours here\u00a0<a href=\"http:\/\/www.goodprint.co.uk\/feedback\/show\/\" target=\"_blank\">http:\/\/www.goodprint.co.uk\/feedback\/show\/<\/a>\u00a0<br>\n\n\n<br>Kind Regards,<br>Customer Service<br>Goodprint UK Ltd<br><a href=\"http:\/\/localwin.goodprint.co.uk\/\" target=\"_blank\">http:\/\/localwin.goodprint.co.uk<\/a><br><br>Follow us online for exclusive discounts, offers and news:\u00a0<br>\n\n<a href=\"http:\/\/twitter.com\/goodprint\" target=\"_blank\">Twitter<\/a>\u00a0|\u00a0<a href=\"http:\/\/www.facebook.com\/pages\/Goodprint-UK-Ltd\/34379572152\" target=\"_blank\">Facebook<\/a>\u00a0|\u00a0<a href=\"http:\/\/localwin.goodprint.co.uk\/blog\/\" target=\"_blank\">Blog<\/a><\/div>\n\n\n<\/div>\n<\/div>\n<\/blockquote><\/div><br><br clear=\"all\"><div><br><\/div>-- <br><div><font face=\"arial, helvetica, sans-serif\" style color=\"#333333\"><b>Lesley Oakey<\/b><\/font><\/div><div><font face=\"arial, helvetica, sans-serif\" style color=\"#333333\">Senior Web Developer\u00a0<\/font><\/div>\n\n<div><font face=\"arial, helvetica, sans-serif\" style color=\"#333333\">Goodprint UK Limited<br>(01842)\u00a0770661<br><a href=\"http:\/\/www.goodprint.co.uk\" target=\"_blank\">www.goodprint.co.uk<\/a><\/font><br style>\n<br><\/div>\n<\/div><\/div><\/div>\n",
  "Tag": "",
  "Headers": [
    {
      "Name": "X-Spam-Checker-Version",
      "Value": "SpamAssassin 3.3.1 (2010-03-16) onrs-ord-pm-inbound1.wildbit.com"
    },
    {
      "Name": "X-Spam-Status",
      "Value": "No"
    },
    {
      "Name": "X-Spam-Score",
      "Value": "2.4"
    },
    {
      "Name": "X-Spam-Tests",
      "Value": "DKIM_SIGNED,DKIM_VALID,DKIM_VALID_AU,FREEMAIL_FROM,HTML_IMAGE_ONLY_28,HTML_MESSAGE,SPF_PASS,T_REMOTE_IMAGE,URIBL_BLACK,URIBL_BLOCKED"
    },
    {
      "Name": "Received-SPF",
      "Value": "Pass (sender SPF authorized) identity=mailfrom; client-ip=209.85.216.177; helo=mail-qc0-f177.google.com; envelope-from=lesley.oakey@gmail.com; receiver=f4168da7d9408ae84a456ae3e94c8d97@inbound.postmarkapp.com"
    },
    {
      "Name": "DKIM-Signature",
      "Value": "v=1; a=rsa-sha256; c=relaxed\/relaxed;        d=gmail.com; s=20120113;        h=mime-version:x-received:in-reply-to:references:date:message-id         :subject:from:to:content-type;        bh=HOSN\/qKRZrT7JhXv7vsoG3HtOxwGgJ46T\/8mXIV619E=;        b=LIsfKe4YC5SmmqW8OlUObux0ZQicoUvYFwQx4QHEPyvNndtaRBN4a+kQ8kr\/bTLTPp         EP+nOYgmz8JvQTaHEl11hyzS80PSeyJXnhGngM0GmPlTtjUekHCc+YMF\/Xt8F\/OtpMxb         tpEmsAnhi9u6F3tTS6wUq+px4H3DczNsHBTJBWdHJdTaaFFP\/GsSSpc6cxxnv5kcdLl\/         InKwNTBCCkz1lsRcicWvAwIIFNDZNX82\/S4lmF1LRUJ+hHxx5IzxIg56wn8cO0DgH2wM         qqRR+2CWCYu4q3bcbgw31YqJ\/hpYFs4vEfbsM7MOGBZIPb0cvW06K+6bFFpgRNzeNC3R         oaOA=="
    },
    {
      "Name": "MIME-Version",
      "Value": "1.0"
    },
    {
      "Name": "X-Received",
      "Value": "by 10.224.32.137 with SMTP id c9mr4358171qad.66.1365068454513; Thu, 04 Apr 2013 02:40:54 -0700 (PDT)"
    },
    {
      "Name": "In-Reply-To",
      "Value": "<CAGGx_jaM4ckVM6TBvgV1Xx3qPmTtOb2hOt3o16F5SFen_5HD_Q@mail.gmail.com>"
    },
    {
      "Name": "References",
      "Value": "<CAGGx_jaM4ckVM6TBvgV1Xx3qPmTtOb2hOt3o16F5SFen_5HD_Q@mail.gmail.com>"
    },
    {
      "Name": "Message-ID",
      "Value": "<CAGGx_jbdCFXvAG6kHznOvcc_QzQ7eY2iCb+RiQD_VNrAerm+AA@mail.gmail.com>"
    }
  ],
  "Attachments": []
}';
    
  }
  
  function _test_attachment()
  {
    return '{
    "From": "lesley.oakey@gmail.com",
    "FromFull": {
      "Email": "lesley.oakey@gmail.com",
      "Name": "Lesley Oakey"
    },
    "To": "f4168da7d9408ae84a456ae3e94c8d97@inbound.postmarkapp.com",
    "ToFull": [
      {
        "Email": "f4168da7d9408ae84a456ae3e94c8d97@inbound.postmarkapp.com",
        "Name": ""
      }
    ],
    "Cc": "",
    "CcFull": [],
    "ReplyTo": "",
    "Subject": "My file for checking",
    "MessageID": "c207e783-cb22-4584-bcab-4bda6687b447",
    "Date": "Thu, 4 Apr 2013 13:51:21 +0100",
    "MailboxHash": "",
    "TextBody": "Designs attached as requested\n",
    "HtmlBody": "<div dir=\"ltr\">Designs attached as requested<br clear=\"all\"><div><br><\/div>\n<\/div>\n",
    "Tag": "",
    "Headers": [
      {
        "Name": "Received-SPF",
        "Value": "Pass (sender SPF authorized) identity=mailfrom; client-ip=209.85.128.41; helo=mail-qe0-f41.google.com; envelope-from=lesley.oakey@gmail.com; receiver=f4168da7d9408ae84a456ae3e94c8d97@inbound.postmarkapp.com"
      },
      {
        "Name": "DKIM-Signature",
        "Value": "v=1; a=rsa-sha256; c=relaxed\/relaxed;        d=gmail.com; s=20120113;        h=mime-version:x-received:date:message-id:subject:from:to         :content-type;        bh=zCYvy+dPEEK43jptWQbq+RnRat94L\/N1CJqMOylq1Cs=;        b=rCE9a7qB4N8sU5P5WFu0C8Ggkrt5Vy2GjZERwVAfFvyzNapMz0mu5rZjB1zjI\/+HWi         e457BDSmO7qyrLXrHLbbBOW+u0iubi1WrGlrZmsBkAgkvIdQVcj8It6LJ+sJ1AQoCQtv         CDk6m+rNf8fIbJH4OEJ7PjLkcuVq2g65td2kCw\/eQ4Jl64eFo76ClziBwl1maZyxL5ww         0n2X3or9misruWLxbEiGLXuUfSpRVJLfEsyCmvgIuFDVHgKvQoXpdu68cgYJUH2BX5Na         i\/ybdydIGoI+YPFazzgKi5nOYXGEPMlAP5W4\/dHiINfGaSW9x72\/nZ+xZ+WbmkyCgZYf         xqow=="
      },
      {
        "Name": "MIME-Version",
        "Value": "1.0"
      },
      {
        "Name": "X-Received",
        "Value": "by 10.49.48.43 with SMTP id i11mr5138960qen.65.1365079882309; Thu, 04 Apr 2013 05:51:22 -0700 (PDT)"
      },
      {
        "Name": "Message-ID",
        "Value": "<CAGGx_jYJ=9MeT+5gdRdsuLFo1sYjD5FKjNz6rHqNKfJJSZe5_A@mail.gmail.com>"
      }
    ],
    "Attachments": [
      {
        "Name": "93550_Cülleen_Front-01.jpg",
        "ContentType": "image/jpeg",
        "Content":"/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAUDBAQEAwUEBAQFBQUGBwwIBwcHBw8LCwkMEQ8SEhEPERETFhwXExQaFRERGCEYGh0dHx8fExciJCIeJBweHx7/2wBDAQUFBQcGBw4ICA4eFBEUHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh7/wAARCAAwADADASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDg7nQ9XlmtLWPTbku9wg5jIA5zknsPevbvBXhjTdPRXvJUubleW5+RT7D+prlRq32WFpWb5+w9qzrTxFMrqyySlyMhR1J/Dmvl62aNyUGtArNqXLE9+tNSs4IFRpB6cdfpWX4slsdQszGzblUhlZTgow6Mp9Qa8lXxFqEeQ0bRrkACVtgyfYkUsmu3sbhneHDHgeepz9eaueLpyp8ljD2VTojSuLhNQeWz1FwL+3+5Oox5qdmI/nWDdu0bPC55U4NUp9Tkn1aGSMoZERg21wfl69vxrT0fTpPEV61skyx3WwtFu4D46g+9eLWwbrNKC978z0sFiJUXyVNvyOR1q4un1E2sTFV3Dn2xzVPxN4qbwwZoI7Ca5iwpMm8ouCDnBHNb99FG80h2jcQPyq9p+lQanb7poEkOMHcM9AAQfyruwc4OpaSuPl/eNI47Rtf0DxKkV4iNFcQuu+OX1/vA/wAQ7ZFHjrxNc6fbQWfh+wM9wSz7kiL+SvQHb6nP6VoeJfDjxTi80i1U/ZQdwGApXHIAHXFS+EdNuJ7U6vfwqUuiDHsJ3bBwMg/nXalBS5re72OhwbjYzfAEfiImK91i6maWSVd8WFVQp4xwOeDzXXeE79tO8W2xHL293sI/2Twf0JrVuLa1t7FZFUgLgjjvmuZmRk8bXAU9HLfjsJrGtUftIzRxV4bF+ewvjI0hsZyFPeM4xT7HUlsLS6OGKqpfjqB3rqvFOszaZZkQ8tIhB78e3vXl15qpgnWGGPDj5pFJyCT2zWMMHKmlNanRUfNLmOl0nVbTW7XYsvkRHtnDGpgINKgCQXO4AdCcjFc5p9iNzTonlbuVQnpV9bIRQt50oZic5zx+FdShJo1UtCZtTfUNXsrN+I3cMwHcDn+lJApk8QXt86OyKG5UZxngH8s0y3gjF0bu3kTzQhVQTwuRjNa/hieDS4/MEglcH98c/eHfHtRTpKVVKWyOetG7uj//2Q==",
        "ContentLength":1357
      },
      {
        "Content":"/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAUDBAQEAwUEBAQFBQUGBwwIBwcHBw8LCwkMEQ8SEhEPERETFhwXExQaFRERGCEYGh0dHx8fExciJCIeJBweHx7/2wBDAQUFBQcGBw4ICA4eFBEUHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh7/wAARCAAwADADASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDg7nQ9XlmtLWPTbku9wg5jIA5zknsPevbvBXhjTdPRXvJUubleW5+RT7D+prlRq32WFpWb5+w9qzrTxFMrqyySlyMhR1J/Dmvl62aNyUGtArNqXLE9+tNSs4IFRpB6cdfpWX4slsdQszGzblUhlZTgow6Mp9Qa8lXxFqEeQ0bRrkACVtgyfYkUsmu3sbhneHDHgeepz9eaueLpyp8ljD2VTojSuLhNQeWz1FwL+3+5Oox5qdmI/nWDdu0bPC55U4NUp9Tkn1aGSMoZERg21wfl69vxrT0fTpPEV61skyx3WwtFu4D46g+9eLWwbrNKC978z0sFiJUXyVNvyOR1q4un1E2sTFV3Dn2xzVPxN4qbwwZoI7Ca5iwpMm8ouCDnBHNb99FG80h2jcQPyq9p+lQanb7poEkOMHcM9AAQfyruwc4OpaSuPl/eNI47Rtf0DxKkV4iNFcQuu+OX1/vA/wAQ7ZFHjrxNc6fbQWfh+wM9wSz7kiL+SvQHb6nP6VoeJfDjxTi80i1U/ZQdwGApXHIAHXFS+EdNuJ7U6vfwqUuiDHsJ3bBwMg/nXalBS5re72OhwbjYzfAEfiImK91i6maWSVd8WFVQp4xwOeDzXXeE79tO8W2xHL293sI/2Twf0JrVuLa1t7FZFUgLgjjvmuZmRk8bXAU9HLfjsJrGtUftIzRxV4bF+ewvjI0hsZyFPeM4xT7HUlsLS6OGKqpfjqB3rqvFOszaZZkQ8tIhB78e3vXl15qpgnWGGPDj5pFJyCT2zWMMHKmlNanRUfNLmOl0nVbTW7XYsvkRHtnDGpgINKgCQXO4AdCcjFc5p9iNzTonlbuVQnpV9bIRQt50oZic5zx+FdShJo1UtCZtTfUNXsrN+I3cMwHcDn+lJApk8QXt86OyKG5UZxngH8s0y3gjF0bu3kTzQhVQTwuRjNa/hieDS4/MEglcH98c/eHfHtRTpKVVKWyOetG7uj//2Q==",
        "ContentLength":1357,
        "ContentType":"image/jpeg",
        "Name":"cat.jpeg"
      }
    ]
  }';
    
  }
    
}