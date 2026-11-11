import page403 from './components/403'
export default{
        components:{
            page403
        },
		computed: {
			settingMenu(){
				return this.abilities.regions
			},
            state () {
                return this.store.state
            },
            store () {
                return this.$store
            },
            abilities(){
                return this.state.abilities
            },
            master(){
                return this.state.master
            },
            distributor(){
                return this.state.distributor
            },
            superStockist(){
                return this.state.superStockist
            },
            dealer(){
                return this.state.dealer
            },
            customer(){
                if(this.state.distributor)
                    return this.state.distributor
                if(this.state.dealer)
                    return this.state.dealer
                if(this.state.superStockist)
                    return this.state.superStockist
            },
            isCustomer(){
                if(this.distributor || this.dealer || this.superStockist)
                    return true
                return false
            }
        },
        filters:{
            format_date_unix(u){
                return moment.unix(u).format('DD MMM YYYY')
            },
            toFixed(num,dec){

                if(!dec)
                    dec = 2
                return parseFloat(num).toFixed(dec)
            }
        }
}