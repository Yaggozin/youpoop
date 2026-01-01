<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
<style>
    body {
        background-color: #F8F8F8;
        margin: 0;
    }

    button {
        font-family: Arial, Helvetica, sans-serif;
        font-weight: normal;
        color: #333;
        text-shadow: 0 1px 1px rgba(255,255,255,.5);
        background-color: #ccc;
        background-repeat: no-repeat;
        border: 1px solid #ccc;
        cursor: pointer;
        -webkit-border-radius: 4px;
        -moz-border-radius: 4px;
        border-radius: 4px;
        -webkit-box-shadow: 0 1px 0 rgba(255,255,255,.5);
        -moz-box-shadow: 0 1px 0 rgba(255,255,255,.5);
        box-shadow: 0 1px 0 rgba(255,255,255,.5);
        background-image: linear-gradient(#fff,#ddd);
        width: 60%;
        padding: 9px;
        box-sizing: border-box;
        margin-bottom: 10px;
        font-size: 13px;

        width: 100%;
    }

    button:hover {
        text-decoration: none;
        background-color: #d8d8d8;
        background-image: -khtml-gradient(linear,left top,left bottom,from(#f8f8f8),to(#d8d8d8));
        background-image: -moz-linear-gradient(#f8f8f8,#d8d8d8);
        background-image: -ms-linear-gradient(#f8f8f8,#d8d8d8);
        background-image: -webkit-gradient(linear,left top,left bottom,color-stop(0%,#f8f8f8),color-stop(100%,#d8d8d8));
        background-image: -webkit-linear-gradient(#f8f8f8,#d8d8d8);
        background-image: -o-linear-gradient(#f8f8f8,#d8d8d8);
        background-image: linear-gradient(#f8f8f8,#d8d8d8);
        filter: progid:DXImageTransform.Microsoft.gradient(enabled=false);
        border-color: #bbb;
    }

    input {
        background: white;
        background-color: white;
        outline: 0;
        background-color: #fff;
        border: 1px solid #ccc;
        -webkit-border-radius: 3px;
        -moz-border-radius: 3px;
        border-radius: 3px;
        border: 1px solid #ccc;
        -webkit-box-shadow: inset 0 1px 0 #eee,#fff 0 1px 0;
        -moz-box-shadow: inset 0 1px 0 #eee,#fff 0 1px 0;
        box-shadow: inset 0 1px 0 #eee,#fff 0 1px 0;
        color: #999;
        font-family: Arial, Helvetica, sans-serif;
        font-weight: normal;
        width: 60%;
        padding: 9px;
        box-sizing: border-box;
        margin-bottom: 10px;
        font-size: 13px;

        width: 100%;
    }

    .container {
        margin: auto 460px;
        padding: 100px 0px;
    }

    .submit {

        color: #fff;
        text-shadow: 0 -1px 1px rgba(0,0,0,.25);
        background-color: #019ad2;
        background-repeat: repeat-x;
        background-image: -khtml-gradient(linear,left top,left bottom,from(#33bcef),to(#019ad2));
        background-image: -moz-linear-gradient(#33bcef,#019ad2);
        background-image: -ms-linear-gradient(#33bcef,#019ad2);
        background-image: -webkit-gradient(linear,left top,left bottom,color-stop(0%,#33bcef),color-stop(100%,#019ad2));
        background-image: -webkit-linear-gradient(#33bcef,#019ad2);
        background-image: -o-linear-gradient(#33bcef,#019ad2);
        background-image: linear-gradient(#33bcef,#019ad2);
        filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#33bcef',endColorstr='#019ad2',GradientType=0);
        border-color: #057ed0;
        -webkit-box-shadow: inset 0 1px 0 rgba(255,255,255,.1);
        -moz-box-shadow: inset 0 1px 0 rgba(255,255,255,.1);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.1);

        -webkit-box-shadow: 0 1px 0 #fff;
        -moz-box-shadow: 0 1px 0 #fff;
        box-shadow: 0 1px 0 #fff;
    }

    .submit:hover {
        color: #fff;
        background-color: #0271bf;
        background-repeat: repeat-x;
        background-image: -khtml-gradient(linear,left top,left bottom,from(#2daddc),to(#0271bf));
        background-image: -moz-linear-gradient(#2daddc,#0271bf);
        background-image: -ms-linear-gradient(#2daddc,#0271bf);
        background-image: -webkit-gradient(linear,left top,left bottom,color-stop(0%,#2daddc),color-stop(100%,#0271bf));
        background-image: -webkit-linear-gradient(#2daddc,#0271bf);
        background-image: -o-linear-gradient(#2daddc,#0271bf);
        background-image: linear-gradient(#2daddc,#0271bf);
        filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#2daddc',endColorstr='#0271bf',GradientType=0);
        border-color: #096eb3;
    }

</style>
</head>
<body>
    <div class="container">
        <button>Twitter Button Style</button>
        <button class="submit">Twitter Button Style Submit</button>
        <input type="text" placeholder="Twitter Input Style">
    </div>
</body>
</html>