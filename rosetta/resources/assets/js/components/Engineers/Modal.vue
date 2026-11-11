<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
	        		<h4 class="modal-title" v-if="currentEngineer.id">Edit Engineer</h4>
	        		<h4 class="modal-title" v-else>Add Installer</h4>
	      		</div>
	      		<div class="modal-body">
	      			<div class="row">
	      				<div class="col-md-12">
		        			<div class="form-group" :class="{'has-error':errors.has('currentEngineer.name')}">
		        				<label>Name *</label>
		        				<input class="form-control" v-model="currentEngineer.name" v-validate="'required'" data-vv-name="currentEngineer.name">
		        			</div>
		        			<div class="form-group" :class="{'has-error':errors.has('currentEngineer.phone_no')}">
		        				<label>Phone *</label>
		        				<input class="form-control" v-model="currentEngineer.phone_no" v-validate="'phone'" data-vv-name="currentEngineer.phone_no">
		        			</div>
		        		</div>
		        	</div>
	      		</div>
	      		<div class="modal-footer">
	        		<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
	        		<button type="button" class="btn btn-primary" @click="editEngineer">Save changes</button>
	      		</div>
	    	</div>
	  	</div>
</div>
</template>
<script>
	import States from '../companies/States'
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus'
	import phoneValidator from '../../validators/phoneNumber.js'
	import { Validator } from 'vee-validate';
	Validator.extend('phone',phoneValidator)
	
	export default {
		props:{
			engineer:{
				type:Object,
				required:false,
				default:function(){
					return {
						id:null,
						phone_no: "",
						name: "",
					}
				}
			},
			company:{
				type:Object,
				required:true,
			},
			distributor:{
				type:Object,
				required:false,
			},
			dealer:{
				type:Object,
				required:false,
			},
			retailCustomer:{
				type:Object,
				required:false,
			}
		},
		components:{
			States
		},
		data(){
			return {
				currentEngineer:{
					id:null,
					phone_no: "",
					name: "",
				}
			}
		},
		mounted(){
			let self = this
			$(this.$refs.modal).on('show.bs.modal', function (e) {
  				if(self.engineer.id)
  				{
  					self.currentEngineer = Object.assign({},self.engineer)
  				}
  				self.errors.clear()
			})
			$(this.$refs.modal).on('hidden.bs.modal', function (e) {
				self.currentEngineer.id = null
				self.currentEngineer.phone_no =  ""
				self.currentEngineer.name =  ""
  				self.errors.clear()
			})
		},
		methods:{
			saveData(){
				let out = {}
				out.phone_no = this.currentEngineer.phone_no
				out.name = this.currentEngineer.name
				return out
			},
			editEngineer(){
				let self = this
				let url = ""
				let method = "post"
				this.$validator.validateAll()
				.then(value => {
					if(value)
					{
						if(self.distributor && self.distributor.id)
						{
							if(self.currentEngineer.id)
							{
								method = "put"
								url = `companies/${self.company.id}/distributors/${self.distributor.id}/engineers/${self.currentEngineer.id}`;
							}
							else
							{
								url = `companies/${self.company.id}/distributors/${self.distributor.id}/engineers`;	
							}
							
						}
						if(self.dealer && self.dealer.id)
						{
							if(self.currentEngineer.id)
							{
								method = "put"
								url = `companies/${self.company.id}/dealers/${self.dealer.id}/engineers/${self.currentEngineer.id}`;
							}
							else
							{
								url = `companies/${self.company.id}/dealers/${self.dealer.id}/engineers`;	
							}
							
						}
						if(self.retailCustomer && self.retailCustomer.id)
						{
							if(self.currentCustomer.id)
							{
								method = "put"
								url = `companies/${self.company.id}/retailCustomers/${self.retailCustomer.id}/engineers/${self.currentCustomer.id}`;
							}
							else
							{
								url = `companies/${self.company.id}/retailCustomers/${self.retailCustomer.id}/engineers`;	
							}
							
						}
						EventBus.$emit('loader:show')
						Vue.axios[method](url,self.saveData())
						.then(response => {
							EventBus.$emit('loader:hide')
							if(self.currentEngineer.id)
							{
								self.$emit('engineer:edited',response.data)
							}
							else
							{
								self.$emit('engineer:added',response.data)
							}
							$(self.$refs.modal).modal('hide')
						})		
					}
					else
					{
						swal({
							text:"Name & Phone are required.",
							icon:"error"
						})
					}
				})
			},
			show(){
				$(this.$refs.modal).modal('show')
			}
		}
	}
</script>