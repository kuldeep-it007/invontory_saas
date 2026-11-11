<template>
	<div v-if="abilities.viewDiscounts">
		<section class="content-header">
			<h1>Discounts <span class="pull-right"><button class="btn btn-sm btn-primary" @click="addDiscount" v-if="abilities.manageDiscounts">ADD</button></span></h1>
		</section>
		<section class="content">
			<div class="table-responsive">
				<discounts ref="Discounts"></discounts>
			</div>
			<discount-modal ref="discountModal" @discount:added="discountAdded" v-if="abilities.manageDiscounts"></discount-modal>
		</section>
	</div>
	<div v-else>
		<page403></page403>
	</div>
</template>
<script>
	import appMixin from '../../vuex-mixin.js'
	import Discounts from './List'
	import DiscountModal from './Modal'
	export default{
		mixins : [appMixin],
		components:{
			Discounts,
			DiscountModal,
		},
		methods:{
			addDiscount(){
				this.$refs.discountModal.show()
			},
			discountAdded(){
				this.$refs.Discounts.reload()
			}
		}
	}
</script>