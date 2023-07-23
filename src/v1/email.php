<?php 

function email($to, $from, $fromName, $subject, $emailText, $files): bool
{

    // Header for sender info
    $headers = "From: $fromName"." <".$from.">";

    // Boundary
    $semiRand = md5(time());
    $mimeBoundary = "==Multipart_Boundary_x{$semiRand}x";

    // Headers for attachment
    $headers .= "\nMIME-Version: 1.0\n" . "Content-Type: multipart/mixed;\n" . " boundary=\"$mimeBoundary\"";

    // Multipart boundary
    $message = "--$mimeBoundary\n" . "Content-Type: text/html; charset=\"UTF-8\"\n" .
    "Content-Transfer-Encoding: 7bit\n\n" . $emailText . "\n\n";

    // preparing attachments
    if(count($files) > 0){
        for($i=0;$i<count($files);$i++){
            if(is_file($files[$i])){
                $message .= "--$mimeBoundary\n";
                $fp =    @fopen($files[$i],"rb");
                $data =  @fread($fp,filesize($files[$i]));

                @fclose($fp);
                $data = chunk_split(base64_encode($data));
                $message .= "Content-Type: application/octet-stream; name=\"".basename($files[$i])."\"\n" .
                "Content-Description: ".basename($files[$i])."\n" .
                "Content-Disposition: attachment;\n" . " filename=\"".basename($files[$i])."\"; size=".filesize($files[$i]).";\n" .
                "Content-Transfer-Encoding: base64\n\n" . $data . "\n\n";
            }
        }
    }

    $message .= "--$mimeBoundary--";
    $return_path = "-f" . $from;

    // Send email and return status
    return @mail($to, $subject, $message, $headers, $return_path);
}