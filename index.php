<!DOCTYPE HTML>
<html>
<head>
    <!--BEGIN HEAD MATE-->
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="viewport" content="initial-scale=1.0,width=device-width"/>
    <meta name="keywords" content="ZHIHUA·WEI,个人简历主页,多版本简历">
    <meta name="description"
          content="非你莫属_ZHIHUA.WEI个人简历主页。本项目用于参与码云举办的“非你莫属”为题的Pages简历模板/个性主页征集活动。本项目一共开发了PHP和HTML两种语言版本，前端工程师和后端工程师皆可使用。开源是一种精神！为中国的互联网行业发展献出一份小小的力量。">
    <meta name="author" content="ZHIHUA·WEI">
    <meta name="version" content="1.0.0">
    <!--END HEAD MATE-->

    <!--BEGIN HEAD LINK SHORTCUT ICON-->
    <link rel="shortcut icon" href="images/icon/zhihuawei_favicon32x32.ico">
    <!--END HEAD LINK SHORTCUT ICON-->

    <!--BEGIN HEAD LINK STYLE-->
    <!--BASE STYLE CSS-->
    <link rel="stylesheet" href="css/style.css" type="text/css" media="screen"/>
    <!--PRODUCTION PHOTO CSS-->
    <link rel="stylesheet" href="css/productionPhoto.css" type="text/css" media="screen"/>
    <!--PRINT CSS-->
    <link rel="stylesheet" href="css/print.css" type="text/css" media="print"/>
    <!--END HEAD LINK STYLE-->

    <!--BEGIN HEAD TITLE-->
    <title>RESUME-ZHIHUA·WEI</title>
    <!--END HEAD TITLE-->
</head>
<body>

<!--BEGIN STICKER-->
<div id="sticker"></div>
<!--END STICKER-->

<!--BEGIN WRAPPER-->
<div id="wrapper">
    <?php
    /**
     * ===============================================
     * Created by ZHIHUA·WEI.
     * Author: ZHIHUA·WEI <zhihua_wei@foxmail.com>
     * Date: 2017/12/20 0001
     * Time: Am 10:23
     * Project: ZHIHUA.WEI Resume
     * Version: 1.0.0
     * Power: Resume Index
     * ===============================================
     */

    //THE FOLLOWING MODULE ARE
    //BROKEN UP INTO SEPARATE FILES
    //YOU CAN REARRANGE THEM HERE
    include('module/english/baseinfo.php');
    include('module/english/skills.php');
    include('module/english/experience.php');
    include('module/english/education.php');
    include('module/english/honors_awards.php');
    include('module/english/as_seen_on.php');
    include('module/english/recommendations.php');
    include('module/english/contact.php');
    ?>
</div>
<!--END WRAPPER-->
<!--COPYRIGHT-->
<div id="copyright">&copy; 2017 - Designed and developed by
    <a href="http://resume.zhihuawei.xyz/" target="_blank" title="ZHIHUA·WEI">ZHIHUA·WEI</a><br>
    英文版&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;<a href="index_ch.php" title="中文版">中文版</a>&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;<a href="index.html" title="HTML版">HTML版</a>
</div>
<!--END COPYRIGHT-->

<!--BEGIN SCRIPTS-->
<!--BASIC JQUERY JS-->
<script src="js/jquery.js"></script>
<!--PROJECT PHOTO JS-->
<script src="js/projectPhoto.js"></script>
<!--BACK POSITION JS-->
<script src="js/backPosition.js"></script>
<!--CUSTOM JS-->
<script src="js/custom.js"></script>
<!--END SCRIPTS-->

</body>
</html>