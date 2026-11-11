<template>
<!-- Modal -->
    <div class="modal fade" ref="modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog" role="document">
            <div class="modal-content" style="border: 4px solid #3c8dbc;">
                <div class="modal-body">
                    <h4 class="modal-title" id="myModalLabel" style="text-align: center;margin-bottom: 20px;font-size: 24px;">NOTICE !</h4>
                   <!-- <p>{{state.notice.message}}</p>-->
                     <p style="font-size: 16px;" v-html="state.notice.message"></p>
                     <div style="text-align: center;margin-top: 20px;">
                        <button class="btn btn-primary" @click="ok">OK</button>
                     </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
    import AppMixin from '../vuex-mixin'
    import Vue from 'vue'
    import {EventBus} from '../configs/EventBus'
    import { Validator } from 'vee-validate'
    export default{
        mixins:[AppMixin],
        computed: {
            state () {
                return this.store.state
            }
        },
        methods:{
            show(){
                if(this.state.notice)
                {
                    if(!this.state.notice.message)
                        return;    
                }
                $(this.$refs.modal).modal('show')
            },
            ok(){
                $(this.$refs.modal).modal('hide')
            },
        }
    }
</script>