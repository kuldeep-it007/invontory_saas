export default {
  SET_USER (state, user) {
    state.user = user
  },
  SET_TOKEN (state, token) {
    state.token = token
  },
  SET_ABILITIES (state, abilities) {
    state.abilities = abilities
  },
  SET_MASTER(state,master){
    state.master = master
  },
  SET_DISTRIBUTOR(state,distributor){
    state.distributor = distributor
  },
  SET_SUPER_STOCKIST(state,superStockist){
    state.superStockist = superStockist
  },
  SET_DEALER(state,dealer){
    state.dealer = dealer
  },
  SET_IMPERSONATED(state,d){
    state.impersonated = d
  },
  SET_NOTICE(state,d){
    state.notice = d
  },
  SET_TOKEN_VERIFIED(state,d){
    state.token_verified = d
  },
  RESET(state){
  	state.user = null
  	state.token = null
  }
}