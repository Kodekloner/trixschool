<?php

echo '<style>
	*{
		margin: 0;
		padding: 0;
		box-sizing: border-box;
		list-style: none;
		text-decoration: none;
	}

	.sidebar{
		position: relative;
		z-index: 20;
		height: 105vh;
		width: 22%;
		background-color: #ffffff;
		transition: .3s ease-in-out;
		transition: .3s ease-in;
		
	}

	.sidebar div{
		position: relative;
		height: 10vh;
		background-color: #ffffff;
	}

	.sidebar Img{
		position: absolute;
		height: 50%;
		left: 5%;
		top: 25%;
	}

	.sidebar.changeWidth{
		width: 10%;
		text-align: center;
	}
	.sidebar.showMenu{
		left: 0;
	}

	li a.hideMenuList{
		display: none;
	}

	.sidebar li{
		color: black;
		padding: 10px 10px 10px 30px;
	}

	.selected{
		background: #b6deff;
	
	}

	.sidebar li i{
		color: black;
		margin-right: 10px;
	}

	.sidebar li a{
		color: black;
		text-decoration: none;
	}

	.sidebar li:hover{
		background-color: #b6deff;
		font-weight: 600;
		
		
	}

	.sidebar .cross-icon{
		position: absolute;
		top: 5px;
		right: 10px;
		display: none;
	}

	.sidebar-header{
		display: flex;
	}

	header{
		background: white;
		height: 10%;
		width: 100%;
		display: flex;
		box-shadow: 1px 3px 3px rgb(205, 212, 216);
	}

	.menu-button{
		font-size: 20px;
		cursor: pointer;
		margin-top: 10px;
	}
	.content{
		width: 100%;
		height: 100vh;
	}

	#mobile{
		display: none;
	}

	.content-data{
		background-color: #ffffff;
		padding: 20px;
		height: 84%;
		margin: 2%;
		box-shadow: 1px 3px 5px #aaa;
		border-radius: 5px;
        overflow-y: auto;
       
	}
	
	::-webkit-scrollbar{
		width: 10px;
	}

	::-webkit-scrollbar-track{
		background-color: #f1f1f1;
	}
	
	::-webkit-scrollbar-thumb{
		background-color: #888;
	}


	@media (max-width: 1200px) {
		.sidebar{
			width: 30%;
		}
	}

	@media (max-width: 900px) {

		.sidebar .cross-icon{
			display: block;
			cursor: pointer;
			color: #ccc;
		}

		.backdrop{
			position: absolute;
			background-color: rgb(0, 0, 0,0.7);
			height: 100vh;
			left: -100%;
			width: 100%;
			z-index: 10;
		}

		.backdrop.showBackdrop{
			left: 0;
		}

		#mobile{
			display: block;
		}

		#desktop{
			display: none;
		}

		.sidebar{
			position: absolute;
			width: 30%;
			top: 0;
			left: -100%;
		}
	}

	@media (max-width: 700px) {
		.sidebar{
			width: 40%;
		}
	}

	@media (max-width: 400px) {
		.sidebar{
			width: 60%;
		}

		.header h2{
			font-size: 20px;
		}

		#mobile{
			height: 25px;
			margin-left: 20px;
		}
	}

	@media (max-width: 320px) {
		.sidebar{
			width: 80%;
		}

	}
	


    
    
    
    	@media (max-width: 1200px) {
		.headTxt{
		    font-weight: 600 !important;
		    color: black !important;
		}
		
		.logoImg{
			width: 120px !important;
			height: 50px !important;
		}
	}

	@media (max-width: 900px) {

		.headTxt{
			font-weight: 600 !important;
		    color: black !important;
		}
		.logoImg{
			width: 150px !important;
		}


	}

	@media (max-width: 700px) {
		.headTxt{
			font-width: 600 !important;
			font-size: 25px !important;
		    color: black !important;
		}
		.logoImg{
			width: 150px !important;
		}
	}

	@media (max-width: 400px) {
		.headTxt{
		    font-size: 19px !important;
		   	font-weight: 600 !important;
		    color: black !important;
		}
		.logoImg{
			width: 130px !important;
		}
	}

		
	}

	@media (max-width: 320px) {
	    .headTxt{
		    font-size: 12px !important;
			font-weight: 600 !important;
		    color: black !important;
		}
	}

    
  </style>';
  
  ?>