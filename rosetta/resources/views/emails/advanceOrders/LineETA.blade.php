<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{{config('brand.brand')}}</title>
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700" rel="stylesheet" />
  <style type="text/css">
    /* Outlines the grids, remove when sending 
    table td { border: 1px solid cyan; }*/
    /* CLIENT-SPECIFIC STYLES */
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; font-family:'Source Sans Pro', sans-serif;}
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; }
    /* RESET STYLES */
    img { border: 0; outline: none; text-decoration: none; }
    table { border-collapse: collapse !important; }
    body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
    /* iOS BLUE LINKS */
    a[x-apple-data-detectors] {
      color: inherit !important;
      text-decoration: none !important;
      font-size: inherit !important;
      font-family: inherit !important;
      font-weight: inherit !important;
      line-height: inherit !important;
    }
    /* ANDROID CENTER FIX */
    div[style*="margin: 16px 0;"] { margin: 0 !important; }
    /* MEDIA QUERIES */
    @media all and (max-width:639px){ 
      .wrapper{ width:100%!important; padding: 0 !important; }
      .container{ width:98%!important;  padding: 0 !important; }
      .mobile{ width:100%!important; display:block!important; padding: 0 !important; }
      .books-highlight{ width:100%!important; display:block!important; padding: 0 !important; text-align:center!important; }
      .books-highlight td.books-desc {text-align:center!important;}
      .img{ width:100% !important; height:auto !important; }.align-lft{text-align: left!important;}
      *[class="mobileOff"] { width: 0px !important; display: none !important; }
      *[class*="mobileOn"] { display: block !important; max-height:none !important; }
    }
  </style>    
</head>
<body style="margin:0; padding:0; background-color:#F2F2F2; font-family:'Source Sans Pro', sans-serif!important;">
  <span style="display: block; width: 625px !important; max-width: 625px; height: 1px" class="mobileOff"></span>
  
  <center>
    <table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#F2F2F2">
      <tr>
        <td align="center" valign="top">
          <table width="625" cellpadding="0" cellspacing="0" border="0" class="wrapper">
            <tr>
              <td height="10" style="font-size:10px; line-height:10px;">&nbsp;</td>
            </tr>
            <tr>
              <td height="10" style="font-size:10px; line-height:10px;">&nbsp;</td>
            </tr>
          </table>
          <table width="625" cellpadding="0" cellspacing="0" border="0" class="wrapper">
            <tr>
              <td height="10" style="font-size:10px; line-height:10px;">&nbsp;</td>
            </tr>
            <tr>
              <td align="center" valign="top">
                <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                  <tr>
                    <td align="center" valign="top">
                     {{strtoupper(config('brand.brand'))}}
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td height="10" style="font-size:10px; line-height:10px;">&nbsp;</td>
            </tr>
          </table>
          <table width="625" cellpadding="0" cellspacing="0" border="0" class="wrapper" bgcolor="#FFFFFF">
            <tr>
              <td height="30" style="font-size:10px; line-height:30px;">&nbsp;</td>
            </tr>
            <tr>
              <td align="center" valign="top">
                <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                  <tr>
                    <td align="center" valign="top" style="color:#000000; font-size:1.5em;">
                      
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td height="30" style="font-size:10px; line-height:30px;">&nbsp;</td>
            </tr>
            <tr>
              <td align="center" valign="top">
                <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                  <tr>
                    <td valign="top" style="color:#7f7f7f;">
                      Hi,<br /><br />
                        ETA for Advance Order Line <br><br><strong>{{$advanceItemProductName}}</strong>
                       <br><br>
                      has been set to {{$date}}.
                      <br><br>
                      <a href="{{ config('app.url').'advanceorder/'.$advanceLine->advance_order_id}}">
                        <strong>Click here</strong></a> to view Advance Order.<br><br>
                     Thank You,<br>
                    {{config('brand.brand')}}<br>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td height="30" style="font-size:10px; line-height:30px;">&nbsp;</td>
            </tr>
            <tr>
              <td height="30" style="font-size:10px; line-height:30px;">&nbsp;</td>
            </tr>
            <tr>
              <td height="30" style="font-size:10px; line-height:30px;">&nbsp;</td>
            </tr>
          </table>
          
          <table width="625" cellpadding="0" cellspacing="0" border="0" class="wrapper">
            <tr>
              <td height="10" style="font-size:10px; line-height:10px;">&nbsp;</td>
            </tr>
            <tr>
              <td align="center" valign="top">
                <table width="600" cellpadding="0" cellspacing="0" border="0" class="container">
                  <tr>
                    <td align="center" valign="top" style="color:#a8aab2; font-size:10px;">
                      Copyright &copy;  {{config('brand.brand')}} - All rights reserved 
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td height="10" style="font-size:10px; line-height:10px;">&nbsp;</td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </center>
</body>
</html>