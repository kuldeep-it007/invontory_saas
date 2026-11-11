<template>
    <div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Add {{brand}} User</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" :class="{'has-error':errors.has('name')}">
                                <label>Name</label>
                                <input type="text" class="form-control" v-model="name" v-validate="'required'" data-vv-name="name">
                                <span class="text-danger" v-show="errors.has('name')">Please enter name.</span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                         <!--   <div class="form-group" :class="{'has-error':errors.has('phone')}">
                                <label>Phone</label> (OTP based login Id)
                                <input type="text" class="form-control" v-model="phone" v-validate="'phone|numeric'" data-vv-name="phone">
                                <span class="text-danger" v-show="errors.has('phone')">Please enter valid phone</span>
                            </div>-->                
                                <div class="form-group" :class="{'has-error':errors.has('phone')}">
                                    <label>Phone</label>
                                    <input type="text" class="form-control" v-model="phone" v-validate="`phone:${company.region.country.code}|numeric`" data-vv-name="phone">
                                </div>
                                <span class="text-danger" v-show="errors.has('phone')">Please enter valid phone</span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" :class="{'has-error':errors.has('email')}">
                                <label>Email</label> (login Id)
                                <input type="text" class="form-control" v-model="email" v-validate="'email'" data-vv-name="email">
                                <span class="text-danger" v-show="errors.has('email')">Please enter valid email</span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" :class="{'has-error':errors.has('password')}">
                                <label>Password</label>
                                <input type="text" class="form-control" v-model="password" v-validate="'required|min:8'" data-vv-name="password">
                                <span class="text-danger" v-show="errors.has('password')">Please enter minimum 8 characters</span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Role</label>
                                <div class="form-group">
                                    <input type="radio" v-model="role" value="admin"> Admin &nbsp;&nbsp;
                                    <input type="radio" v-model="role" value="salesRep"> Sales Representative
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-primary" :disabled="(!email && !phone) || (!name)" @click="add">ADD</button>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
    import Vue from 'vue'
    import appMixin from '../../vuex-mixin.js'
    import {EventBus} from '../../configs/EventBus'
    import phoneValidator from '../../validators/phoneNumber.js'
    import VeeValidate from 'vee-validate'
    import { Validator } from 'vee-validate';
    Vue.use(VeeValidate)
    Validator.extend('phone',phoneValidator)
    export default {
        mixins : [appMixin],
        components:{

        },
        props:['user', 'companyId', 'distributorId', 'dealerId', 'super_stockist_id'],
        data(){
            return {
                loaded   :false,
                name     : null,
                phone    : null,
                email    : null,
                password : null,
                role     : null,
                brand:window.config.brand_short,
                company:{
                    id:null,
                    region:{
                        country_code:"",
                        country:{
                            code: null
                        }
                    }
                },
            }
        },
        mounted(){
            //#743
            let self = this
            if(this.distributor)
                this.company = Object.assign({},this.distributor.company)
            else if(this.dealer)
                this.company = Object.assign({},this.dealer.company)
            else if(this.superStockist)
                this.company = Object.assign({},this.superStockist.company)
            else
            {
                this.company.id = this.$route.params.id
                if(this.company)
                {
                    Vue.axios.get(`companies/${this.company.id}`)
                    .then(response => {
                        self.company = Object.assign({},response.data)
                    })
                }
            }
            self.company.region = {country:{
                code: self.company.region.country_code
            }}

            self.company = Object.assign({},self.company)
            //End of #743
            
            $(this.$refs.modal).on('shown.bs.modal', function (e) {
                Vue.nextTick(function(){
                    self.errors.clear()
                })
            })
            $(this.$refs.modal).on('hidden.bs.modal', function (e) {
                self.loaded = false 
                self.email = null
                self.phone = null
                self.name = null
                self.password = null
                self.role = null
            })
        },
        computed:{
            store () {
                return this.$store
            },
            state () {
                return this.store.state
            },
        },
        methods:{
            show(){
                let self = this
                $(self.$refs.modal).modal('show')
            },
            add(){
                let self = this
                let input = {
                    username : self.name,
                    phone : self.phone,
                    email : self.email,
                    password : self.password,
                    role : self.role
                }
                let url = ""
                if(self.distributorId)
                    url = `companies/${self.companyId}/distributors/${self.distributorId}/users`
                if(self.super_stockist_id)
                    url = `companies/${self.companyId}/super_stockists/${self.super_stockist_id}/users`
                if(self.dealerId)
                    url = `companies/${self.companyId}/dealers/${self.dealerId}/users`
                this.$validator.validateAll()
                .then(value => {
                    if(value)
                    {
                        EventBus.$emit('loader:show')
                        Vue.axios.post(url, input)
                        .then(response => {
                            swal({
                                title:"User added!",
                                icon:"success"
                            })
                            .then(value => {
                                self.$emit('user-added',response.data)
                                self.loaded = false
                                $(self.$refs.modal).modal('hide')
                            }) 
                        })
                        .catch(error => {
                            if (error.response.data.email) 
                            {
                                swal ( "Error" ,  error.response.data.email[0] ,  "error" )
                                .then(value => {
                                    self.loaded = false 
                                    self.email = null
                                })
                            }
                            if (error.response.data.phone) 
                            {
                                swal ( "Error" ,  error.response.data.phone[0] ,  "error" )
                                .then(value => {
                                    self.loaded = false 
                                    self.phone = null
                                })
                            }
                        })
                    }
                })     
            },
        }
    }
</script>