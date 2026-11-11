<template>
<!-- Modal -->
    <div class="modal fade" ref="modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" v-if="!changePassword">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="myModalLabel">Reset Password</h4>
                </div>
                <div class="modal-body">
                    <p v-if="changePassword">It is mandatory to change password at this moment.</p>
                    <div class="form-group" :class="errors.has('currentPassword') ? 'has-error' : ''">
                        <label for="currentPassword">Current password</label>
                        <input type="password" class="form-control" v-model="currentPassword" v-validate="'required'" data-vv-name="currentPassword" name="currentPassword">
                        <span class="text-red" v-if="errors.has('currentPassword')">Please enter current password</span>
                    </div>
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
                    <button class="btn btn-primary" @click="resetPassword(state.user.id)">Update</button>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
    import AppMixin from '../vuex-mixin'
    import Vue from 'vue'
    import passwordValidator from '../validators/password.js'
    import {EventBus} from '../configs/EventBus'
    import { Validator } from 'vee-validate'
    Validator.extend('password',passwordValidator)
    export default{
        mixins:[AppMixin],
        props:{
            changePassword:{
                type: Boolean,
                required:false,
                default:false,
            }
        },
        data(){
            return {
                currentPassword:"",
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
                self.currentPassword = ""
            })
            $(this.$refs.modal).on('show.bs.modal', function (e) {
                Vue.nextTick(function(){
                    self.errors.clear()
                })
                
            })
        },
        methods:{
            show(){
                $(this.$refs.modal).modal('show')
            },
            resetPassword(userId){
                let self = this
                let input = {
                        current_password : this.currentPassword,
                        new_password : this.password,
                        confirm_new_password : this.password_confirmation,
                    }
                this.$validator.validateAll()
                .then(result => {
                    if(result)
                    {
                        EventBus.$emit('loader:show')
                        Vue.axios.put(`users/reset/${userId}`,input)
                        .then(response => {
                            swal({
                                title:"Password changed successfully!",
                                icon:"success"
                            })
                            $(this.$refs.modal).modal('hide')
                        })
                        .catch(error => {
                            if(error.response.status == 404)
                            {
                                swal({
                                    title:"Error",
                                    text:"Please enter valid current password",
                                    icon:"error"
                                })
                            }
                        })
                    }
                })
            }
        }
    }
</script>