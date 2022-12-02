<?php
function getid3($filePath){    
    require_once('getid3/getid3.php');
    $getID3 = new getID3;
    $response = $getID3->analyze((is_array($filePath) ? $filePath['tmp_name'] : $filePath));
    if(!isset($response['playtime_string']) AND is_array($filePath)){


        if(pathinfo($filePath['name'], PATHINFO_EXTENSION)=='mp3'){
            require_once('getid3/mp3file.class.php');
            $mp3file = new MP3File($filePath['tmp_name']);
            $response['playtime_string'] = $mp3file->formatTime($mp3file->getDuration());
            $response['fileformat'] = 'mp3';
            $response['mime_type'] = mime_content_type($filePath['tmp_name']);
            unset($response['error']);
        }


    }
    return $response;
}
?>

<!-- <form action="getid3.php" method="post" enctype="multipart/form-data"> -->
    <!-- <input type="file" name="file" accept=".jpeg, .jpg, .png, .webp, .svg, .gif"> -->
    <!-- <input type="file" name="file" accept=".mp3, .wav, .flac, .aac, .m4a"> -->
    <!-- <input type="file" name="file" accept=".mov, .mp4, .webm, .m4v, .mkv"> -->
    <!-- <input type="submit" value="Upload"> -->
<!-- </form>  -->
<?php
// echo '<pre>';
// if(isset($_FILES['file']['tmp_name'])){
//     echo '<pre>';
//     print_r(getid3($_FILES['file']));
//     echo '</pre>';
// }
