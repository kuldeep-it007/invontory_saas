import PhoneNumber from 'awesome-phonenumber'

export default {
  getMessage: field => `${field} is not a valid phone number`,
  validate (value,region) {
    if(!region)
      region = 'IN'
    else
      region = region[0]
    return new Promise(resolve => {
      let phone = new PhoneNumber(value, region);
      resolve({ valid: phone.isValid() })
    })
  }
}