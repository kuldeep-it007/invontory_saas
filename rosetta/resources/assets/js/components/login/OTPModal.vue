<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog" role="document">
    		<div class="modal-content">
      			<div class="modal-header">
        			<h4 class="modal-title">Login via OTP</h4>
      			</div>
      			<div class="modal-body">
      				<div class="row">
      					<div class="col-md-12">
      						<p>We have sent OTP  to mobile no {{phone}}.</p>
      						<div class="form-group" :class="errors.has('otp') ? 'has-error' : ''">
      							<label>OTP</label>
      							<input class="form-control" v-model="otp" v-validate="'required|numeric|length:4'" data-vv-name="otp">
      						</div>
      					</div>
      				</div>
      				<div class="row" v-if="error">
      					<div class="col-md-12">
      						<div class="alert alert-danger">
      							Invalid OTP or OTP has expired
      						</div>
      					</div>
      				</div>
      			</div>
      			<div class="modal-footer">
      				<button class="btn btn-success" @click="login" :disabled="otp.length != 4">Login</button>
      			</div>
      		</div>
      	</div>
    </div>
</template>
<script>
	import {EventBus} from '../../configs/EventBus.js'
	import Vue from 'vue'
	export default {
		data(){
			return {
				token:null,
				otp:"",
				error:false,
				phone: "",
			}
		},
		methods:{
			show(token,phone){
				this.token = token
				this.otp = ""
				this.phone = phone
				$(this.$refs.modal).modal('show')
			},
			login(){
				let self = this
				this.error = false
				EventBus.$emit('loader:show')
				Vue.axios.post('otpLogin',{token:this.token,otp:this.otp})
				.then(response => {
					EventBus.$emit('loader:hide')
					$(this.$refs.modal).modal('hide')
					Vue.nextTick(function(){
						self.$emit('login:otp',response.data)	
					})
				})
				.catch(error => {
					EventBus.$emit('loader:hide')
					this.error = true
				})
			}
		}	

	}
</script>