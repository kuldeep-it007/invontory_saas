<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
  		<div class="modal-dialog modal-lg" role="document">
    		<div class="modal-content">
      			<div class="modal-header">
        			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        			<h4 class="modal-title">Update User</h4>
      			</div>
      			<div class="modal-body">
      				<div class="row">
      					<div class="col-md-5">
                            <div class="form-group" :class="{'has-error':errors.has('phone')}">
                                <label>Phone</label>
                                <input type="text" class="form-control" v-model="user.phone" v-validate="`phone:${company.region.country_code}|numeric`" data-vv-name="phone">
                            </div>
      					</div>
                        <div class="col-md-5">
                            <div class="form-group" :class="{'has-error':errors.has('email')}">
                                <label>Email</label>
                                <input type="text" class="form-control" v-model="user.email" v-validate="'email'" data-vv-name="email">
                                <span class="text-danger" v-show="errors.has('email')">Please enter valid email</span>
                            </div>
                        </div>
      				</div>
      			</div>
                <div class="modal-footer">
                    <button class="btn btn-md btn-primary" :disabled="(!user.email && !user.phone)" @click="update(user.id)">Save</button>
                </div>
      		</div>
      	</div>
    </div>
</template>
<script>
    import Vue from 'vue'
	import VeeValidate from 'vee-validate'
    import {EventBus} from '../../configs/EventBus'
    import phoneValidator from '../../validators/phoneNumber.js'
    import { Validator } from 'vee-validate';
    Validator.extend('phone',phoneValidator)
    Vue.use(VeeValidate)
	export default {
      	components:{
		},
      //  props:['company'],
		data(){
			return {
                user:{
                    phone:null,
                    email:null,
                },
                company:{
                    id:null,
                    region:{
                        country_code:null
                    }
                },
			}
		},
		methods:{
            update(userId){
                let self = this
                let input = {
                    phone : this.user.phone,
                    email: this.user.email
                }
                let url = ""
                url = `users/${userId}`
                this.$validator.validateAll()
                .then(value => {
                    if(value)
                    {
                        EventBus.$emit('loader:show')
                        Vue.axios.put(url,input)
                        .then(response => {
                            EventBus.$emit('loader:hide')
                            swal({
                                title:"User updated!",
                                icon:"success"
                            })
                            .then(value => {
                                this.$emit('user-updated',response.data['email'],response.data['phone'])
                                $(self.$refs.modal).modal('hide')
                            }) 
                        })
                        .catch(error => {
                            if(error.response.status == 400)
                            {
                                if(error.response.data['email']) 
                                {
                                    swal ( "Error" ,  "The user email has already been taken",  "error" )
                                    .then(value =>{
                                    })
                                }
                                else if(error.response.data['phone']) 
                                {
                                    swal ( "Error" ,  "The user phone has already been taken",  "error" )
                                    .then(value =>{
                                    })
                                }
                            }
                        })
                    }
                })            
            },
			show(user){
                this.user = Object.assign({},user)
                if(user.dealer)
                    this.company = Object.assign({},user.dealer.company)
                if(user.distributor)
                    this.company = Object.assign({},user.distributor.company)
                if(user.superStockist)
                    this.company = Object.assign({},user.superStockist.company)
				$(this.$refs.modal).modal('show')
            }
		}
	}
</script>

