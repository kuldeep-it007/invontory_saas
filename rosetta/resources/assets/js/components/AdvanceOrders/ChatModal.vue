<template>
    <div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 v-if="item" class="modal-title">
                        <span>{{item.product.category.display_name}} &raquo; {{item.product.collection.display_name}}     
                            <template v-if="item.product.colour"> &raquo; {{item.product.colour.display_name}}
                            </template>
                        </span>
                        <template v-if="!isCustomer"><br>
                            <span class="product-category">{{item.product.category.code}} &raquo; {{item.product.collection.code}}
                                <template v-if="item.product.colour"> &raquo; {{item.product.colour.code}}
                                </template>
                            </span>
                        </template>
                    </h4>
                </div>
                <div class="modal-body direct-chat-primary">
                    <div class="direct-chat-messages" ref="messageContainer" v-if="item">
                      <!-- Message. Default to the left -->
                        <div v-for="(message,index) in messages" :key="message.id">


                            <div class="direct-chat-msg" v-if="state.user.email != message.author.email">
                                <div class="direct-chat-info clearfix">
                                    <span class="direct-chat-name pull-left">{{message.author.name}}</span>
                                    <span class="direct-chat-timestamp pull-right">{{moment(message.created_at).format('DD MMM YYYY hh:mm a')}}</span>
                                </div>
                            <!-- /.direct-chat-info -->
                                <img class="direct-chat-img" src="/images/user_avatar.png" alt="Message User Image"><!-- /.direct-chat-img -->
                                <div class="direct-chat-text">{{message.text}}</div>
                            <!-- /.direct-chat-text -->
                            </div>
                            <!-- /.direct-chat-msg -->


                            <!-- Message to the right -->
                            <div class="direct-chat-msg right" v-if="state.user.email == message.author.email">
                                <div class="direct-chat-info clearfix">
                                    <span class="direct-chat-name pull-right">You</span>
                                    <span class="direct-chat-timestamp pull-left">{{moment(message.created_at).format('DD MMM YYYY hh:mm a')}}</span>
                                </div>
                                <!-- /.direct-chat-info -->
                                <img class="direct-chat-img" src="/images/user_avatar.png" alt="Message User Image"><!-- /.direct-chat-img -->
                                <div class="direct-chat-text">{{message.text}}</div>
                                <!-- /.direct-chat-text -->
                            </div>
                            <!-- /.direct-chat-msg -->


                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="input-group" v-if="abilities.sendAdvanceMessages">
                        <input type="text" v-model="text" placeholder="Type Message ..." class="form-control">
                        <span class="input-group-btn">
                           <!-- <button type="submit" class="btn btn-primary btn-flat">Send</button>-->
                            <button class="btn btn-info btn-block" @click="sendMessage(item)">Send</button>
                        </span>
                    </div>
                </div>
            </div>
            <div class="overlay" v-if="!loaded">
              <i class="fa fa-sync-alt fa-spin"></i>
            </div>
        </div>
    </div>
</template>
<script>
    import Vue from 'vue'
    import appMixin from '../../vuex-mixin.js'
    import {EventBus} from '../../configs/EventBus'
    import VeeValidate from 'vee-validate'
    import moment from 'moment'
    Vue.prototype.moment = moment
    Vue.use(VeeValidate)
    export default {
        mixins : [appMixin],
        props:['item', 'effectiveCompany', 'effectiveDistributor', 'effectiveSuperStockist', 'effectiveDealer'],
        data(){
            return {
                messages:[],
                text:null,
                loaded:false,
            }
        },
        filters: {
            moment: function (date) {
                return moment(date).format('MMMM Do YYYY, h:mm:ss a');
            }
        },
        mounted(){
            let self = this
            $(this.$refs.modal).on('shown.bs.modal', function (e) {
                Vue.nextTick(function(){
                    self.errors.clear()
                })
             //   self.button.text = 'Send'
                self.loadMessages()
            })
            $(this.$refs.modal).on('hidden.bs.modal', function (e) {
                self.messages = []
                self.text = null
                self.loaded = false
            })
        },
        computed:{
        },
        methods:{
            moment: function () {
                return moment();
            },
            show(){
                let self = this
                $(self.$refs.modal).modal('show')
            },
            loadMessages(){
                let self  = this
                let url = ""
                if(self.effectiveDistributor)
                {
                    url = `companies/${self.effectiveCompany.id}/distributors/${self.effectiveDistributor.id}/advance_item/${self.item.id}/messages`
                }
                if(self.effectiveSuperStockist)
                {
                    url = `companies/${self.effectiveCompany.id}/super_stockists/${self.effectiveSuperStockist.id}/advance_item/${self.item.id}/messages`
                }
                if(self.effectiveDealer)
                {
                    url = `companies/${self.effectiveCompany.id}/dealers/${self.effectiveDealer.id}/advance_item/${self.item.id}/messages`
                }
                Vue.axios.get(url)  
                .then(response => {
                    self.loaded = true
                    self.messages = response.data.messages
                    Vue.nextTick(function(){
                        self.$refs.messageContainer.scrollTop = self.$refs.messageContainer.scrollHeight;
                    })
                })
            },
            sendMessage(item){
                let self  = this
                let input = {
                    text: self.text
                }
                let url =""
                if(self.effectiveDistributor)
                {
                    url = `companies/${self.effectiveCompany.id}/distributors/${self.effectiveDistributor.id}/advance_item/${item.id}/messages`
                }
                 if(self.effectiveSuperStockist)
                {
                    url = `companies/${self.effectiveCompany.id}/super_stockists/${self.effectiveSuperStockist.id}/advance_item/${item.id}/messages`
                }
                if(self.effectiveDealer)
                {
                    url = `companies/${self.effectiveCompany.id}/dealers/${self.effectiveDealer.id}/advance_item/${item.id}/messages`
                }
             //    self.button.text = 'Sending'
            //send.push({ text: 'Sending' });
                Vue.axios.post(url, input)  
                .then(response => {
                    self.text = null
                    self.messages.push(response.data)
                    Vue.nextTick(function(){
                        self.$refs.messageContainer.scrollTop = self.$refs.messageContainer.scrollHeight;
                    })
                 //   self.button.text = 'Send'
                })
            },
        }
    }
</script>
<style scoped>
.product-category {
    display: block;
    color: #888;
    font-size: 14px;
}
</style>