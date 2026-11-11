<template>
	<div>
		<div class="notice" v-if="notice">
			<div class="alert" style="background: #d2d6de;">
				<h4 class="text-center">NOTICE !</h4>
				<p v-html="notice.message"></p>
			</div>
		</div>
		<div class="app-wrapper" v-if="!showToken">
			<a :href="appStoreUrl">
    			<img class="bg hidden-xs" :src="desktopAppImg" border="none"/>  
				<img class="bg visible-xs" :src="mobileAppImg" border="none"/>  
			</a>
			<div class="admin-access-desktop">
				<a class="text-black" href="#" @click.prevent="showToken = true">
					<h4>For admin access <br>click here</h4>
				</a>
			</div>
		</div>
		<div class="login-box" :class="notice?'notice-present' : ''" v-if="showToken">
			<div class="login-logo">
				<a href="/"><b>{{siteName}}</b></a>
			</div>
  <!-- /.login-logo -->
			<div class="login-box-body">
			    <p class="login-box-msg">Ordering from Portal will be soon discontinued, please download and use our app to place orders.</p>
				<div class="form-group has-feedback">
					<input type="password" class="form-control" placeholder="Contact Administrator for Token" v-model="token" autocomplete="off">
				</div>
  				<div class="row">
				    <!-- /.col -->
				    <div class="col-xs-12">
				    	<div class="text-center">
				      		<button @click.prevent="validateToken" class="btn btn-success" type="button">Validate Token</button>
				      	</div>
				    </div>
				    <!-- /.col -->
				</div>
				<div class="row" style="margin-top: 5px;" v-if="bag.count()">
					<div class="col-md-12">
						<div class="login-error">
							<div class="alert alert-danger">
								<p v-for="error in bag.all()">{{error}}</p>
							</div>
						</div>
					</div>
				</div>
  			</div>
			<!--<center v-if="tnc">
				<i class="fab fa-cc-visa"></i>
				<i class="fab fa-cc-mastercard"></i>
			</center>-->
  			<center>Powered by Fluid Cube Technologies.</center>
		</div>
	</div>
</template>
<script>
	import Vue from 'vue'
	import { ErrorBag } from 'vee-validate'
	import {EventBus} from '../configs/EventBus'
	import Tnc from './tnc'
	const bag = new ErrorBag()
	export default{
		data(){
			return {
				token:"",
				siteName:window.config.brand,
				bag:bag,
				notice:null,
				showToken:false
			}
		},
		mounted(){
			Vue.axios.get('notice')
			.then(response => {
				this.notice = Object.assign({},response.data)
			})
		},
		computed:{
			isMobile(){
				return /\d{10}/.test(this.email)
			},
			desktopAppImg(){
				return `/images/token_banner/app_desktop_${window.config.prefix}.jpeg`
			},
			mobileAppImg(){
				return `/images/token_banner/app_mobile_${window.config.prefix}.jpeg`
			},
			appStoreUrl(){
				return window.config.mobile_app_url
			}
		},
		methods:{
			showTnc(){
				this.$refs.tnc.show(window.config.prefix)
			},
			validateToken(){
				this.bag.clear()
				let self = this
				EventBus.$emit('loader:show')

				Vue.axios.post('validate_token',{token:self.token})
				.then(response => {
					EventBus.$emit('loader:hide')
					this.$store.commit('SET_TOKEN_VERIFIED',true)
					this.$router.push({'name' : 'login'})
				})
				.catch(error => {
					swal({
						title: "Invalid Token!!",
						text: "Please contact Administrator for valid token",
						icon: "error"
					})
				})
			}
		}
	}
</script>
<style scoped>
.login-box-body{
	background: #d2d6de;
}
.fab{
	font-size: 25px;
}
.login-box.notice-present{
	margin-top: 0px !important;
	margin-bottom: 0px !important;
}
@media (min-width: 768px) {
	.notice{
		width: 700px !important;
		margin: 10px auto 20px;
	}
}
.notice{
		width: 90%;
		margin: 10px auto 20px;
}


.app-wrapper{
    margin: auto;
    height: 100vh;
    width: 100vw;
}

img.bg {
   max-height: 100%;
    height: auto;
    position: fixed;
    left: 0;
    right: 0;
    margin: 0 auto;
    max-width: 100%;
    width: auto;
}
.admin-access-desktop{
	position: absolute;
	right: 20px;
	text-align: center;
}
</style>