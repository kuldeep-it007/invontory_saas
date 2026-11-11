<template>
	<div v-if="abilities.viewStockPointCustomers">
        <section class="content-header">
            <h1>Customers</h1>
        </section>
        <section class="content">
			<customer-list :company="company" v-if="company.id"></customer-list>
		</section>
	</div>
</template>
<script>
	import Vue from 'vue'
    import {EventBus} from '../../configs/EventBus'
    import AppMixins from '../../vuex-mixin'
    import CustomerList from './CustomerList'
	export default{
		mixins: [AppMixins],
		components:{
			CustomerList,
		},
		data(){
			return {
				company:{
					id:null,
				}
			}
		},
		mounted(){
			if(this.$route.params.companyid)
				this.company.id = this.$route.params.companyid
			else
				this.company.id = this.state.user.company.id
		}

	}
</script>