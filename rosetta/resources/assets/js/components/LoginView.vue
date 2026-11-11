<template>
	<div>
		<div class="notice" v-if="notice">
			<div class="alert" style="background: #d2d6de;">
				<h4 class="text-center">NOTICE !</h4>
				<p v-html="notice.message"></p>
			</div>
		</div>
		<div class="login-box" :class="notice?'notice-present' : ''">
			<div class="login-logo">
				<a href="/"><b>{{siteName}}</b></a>
			</div>
  <!-- /.login-logo -->
			<div class="login-box-body">
			    <p class="login-box-msg">Sign in to start your session</p>
				<div class="form-group has-feedback">
					<input type="email" class="form-control" placeholder="Email or 10 digit mobile" v-model="email" autocomplete="off">
					<span class="glyphicon glyphicon-user form-control-feedback"></span>
				</div>
				<div class="form-group has-feedback">
    				<input type="password" class="form-control" placeholder="Password" v-model="password" @keyup.enter="login">
    					<span class="glyphicon glyphicon-lock form-control-feedback"></span>
  				</div>
  				<div class="row">
				    <!-- /.col -->
				    <div class="col-xs-12">
				      	<button @click.prevent="login" class="btn btn-success" type="button">Log in</button>
				      	<button @click.prevent="otpLogin" class="btn btn-primary" type="button" v-if="isMobile">Login via OTP</button>
				    </div>
				    <!-- /.col -->
				</div>
				<!--<div class="row" v-if="tnc">
					<div class="col-xs-12">
						By logging in, you are agreeing to <a href="#" @click.prevent="showTnc">Terms & conditions</a>. Please read <a href="#" @click.prevent="showTnc">Terms & conditions</a> carefully.
					</div>
					<div class="col-xs-12">
						<router-link :to="{name:'about'}" style="margin-left:20px;">About Us</router-link>
					</div>
				</div>-->
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
		<otp-modal ref="otpModal" @login:otp="otpLoginSuccess"></otp-modal>
		<tnc ref="tnc"></tnc>
	</div>
</template>
<script>
	import Vue from 'vue'
	import { ErrorBag } from 'vee-validate'
	import {EventBus} from '../configs/EventBus'
	import OtpModal from './login/OTPModal'
	import Tnc from './tnc'
	const bag = new ErrorBag()
	export default{
		components:{
			OtpModal,
			Tnc
		},
		data(){
			return {
				email:"",
				password:"",
				siteName:window.config.brand,
				bag:bag,
				tnc: window.config.tnc,
				notice:null
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
			}
		},
		methods:{
			showTnc(){
				this.$refs.tnc.show(window.config.prefix)
			},
			otpLoginSuccess(t){
				let token = t.token
				delete(t.token)
				delete(t.change_password)
				this.$store.commit('SET_USER', t)
				this.$store.commit('SET_TOKEN', token)
				this.$router.push('/')
			},
			otpLogin(){
				EventBus.$emit('loader:show')
				Vue.axios.post('otp',{phone:this.email})
				.then(response => {
					EventBus.$emit('loader:hide')
					this.$refs.otpModal.show(response.data.otp_token,this.email)	
				})
				
			},
			login(){
				this.bag.clear()
				let self = this
				EventBus.$emit('loader:show')
				Vue.axios.post('login',{email:self.email,password:self.password})
					.then(response => {
						EventBus.$emit('loader:hide')
						let token = response.data.token
						delete(response.data.token)
						delete(response.data.change_password)
						this.$store.commit('SET_USER', response.data)
						this.$store.commit('SET_TOKEN', token)
						this.$router.push('/')
					})
					.catch(error => {
						swal({
							title: "Error",
							text: "Invalid email or password.",
							icon: "error"
						})
						if(error.response.status == 400)
						{
							if(error.response.data.email)
								this.bag.add('email', error.response.data.email[0],'')
							if(error.response.data.password)
								this.bag.add('password', error.response.data.password[0],'')
						}
						if(error.response.status == 401)
						{
							this.bag.add('auth', "Invalid email or password.",'')
						}
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

</style>