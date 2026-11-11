<template>
<!-- Modal -->
    <div class="modal fade" ref="modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="myModalLabel">{{user.name}} ({{user.email}})</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group" :class="errors.has('password') ? 'has-error' : ''">
                        <label for="password">New password</label>
                        <input type="password" class="form-control" v-model="password" v-validate="'required|min:8'" data-vv-name="password" name="password" ref="password" >
                        <span class="text-red" v-if="errors.has('password')">{{errors.first('password')}}</span>
                    </div>
                     <div class="form-group" :class="errors.has('password_confirmation') ? 'has-error' : ''">
                        <label for="password_confirmation">Password Confirmation</label>
                        <input type="password" class="form-control" v-model="password_confirmation" v-validate="'required|confirmed:password'" data-vv-name="password_confirmation" name="password_confirmation" data-vv-as="password" >
                        <span class="text-red" v-if="errors.has('password_confirmation')">The password confirmation does not match</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" @click="resetPassword(user.id)">Reset</button>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
    import AppMixin from '../../vuex-mixin'
    import Vue from 'vue'
    import passwordValidator from '../../validators/password.js'
    import {EventBus} from '../../configs/EventBus'
    import { Validator } from 'vee-validate'
    Validator.extend('password',passwordValidator)
    export default{
        mixins:[AppMixin],
        data(){
            return {
                user:{},
                password:"",
                password_confirmation:""
            }
        },
        computed: {
            state () {
                return this.store.state
            }
        },
        mounted(){
            let self = this
            $(this.$refs.modal).on('hidden.bs.modal', function (e) {
                self.password = ""
                self.password_confirmation = ""
            })
            $(this.$refs.modal).on('show.bs.modal', function (e) {
                Vue.nextTick(function(){
                    self.errors.clear()
                })
            })
        },
        methods:{
            show(user){
                this.user = Object.assign({},user)
                $(this.$refs.modal).modal('show')
            },
            resetPassword(userId){
                let self = this
                let input = {
                        new_password : this.password,
                        confirm_new_password : this.password_confirmation,
                    }
                this.$validator.validateAll()
                .then(result => {
                    if(result)
                    {
                        EventBus.$emit('loader:show')
                        Vue.axios.put(`users/resetCustomerPassword/${userId}`,input)
                        .then(response => {
                            swal({
                                title:"Password changed successfully!",
                                icon:"success"
                            })
                            $(this.$refs.modal).modal('hide')
                        })
                        .catch(error => {})
                    }
                })
            }
        }
    }
</script>