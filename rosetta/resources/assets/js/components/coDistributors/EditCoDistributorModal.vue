<template>
	<div class="modal fade" tabindex="-1" role="dialog" ref="modal" data-backdrop="static">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Edit Co Distributor</h4>
                </div>
                <div class="modal-body" v-if="co_distributor">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group" :class="errors.has('credit_limit') ? 'has-error':''">
                                <label>Credit limit</label>
                                <input v-model="credit_limit" class="form-control" v-validate="'required|decimal'" data-vv-name="credit_limit">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group" :class="errors.has('overdue_balance') ? 'has-error':''">
                                <label>Overdue Balance</label>
                                <input v-model="overdue_balance" class="form-control" v-validate="'required|decimal'" data-vv-name="overdue_balance">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" @click="set">SET</button>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
	import Vue from 'vue'
	import {EventBus} from '../../configs/EventBus.js'
	import appMixin from '../../vuex-mixin.js'
	export default{
		mixins:[appMixin],
		data(){
			return {
				co_distributor:null,
				credit_limit:0,
				overdue_balance:0

			}
		},
		methods:{
			set(){
				this.$validator.validateAll()
				.then(v => {
					if(v)
					{
						let url = `companies/${this.distributor.company.id}/distributors/${this.distributor.id}/co_distributors?type=2`
						EventBus.$emit('loader:show')
						Vue.axios.put(url,{distributor_id:this.co_distributor.id,credit_limit:this.credit_limit,overdue_balance:this.overdue_balance})
						.then(response => {
							EventBus.$emit('loader:hide')
							this.$emit('co-distributor:updated')
							$(this.$refs.modal).modal('hide')
						})
					}
				})

			},
			show(dist){
				this.co_distributor = Object.assign(dist)
				this.credit_limit = dist.credit_limit
				this.overdue_balance = dist.overdue_balance
				$(this.$refs.modal).modal('show')
			}
		}
	}
</script>