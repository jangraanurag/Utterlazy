/**
 * Ola Maps API Integration
 * Autocomplete, Geocoding, Reverse Geocoding
 */

const OLA_API_KEY = 'WoKIcPjKhh1a09ugBYDPrUW9QHjLNDrW6izkx25d'
const OLA_BASE = 'https://api.olamaps.io/places/v1'

/**
 * Autocomplete - search for places as user types
 * @param {string} input - Search query
 * @returns {Promise<Array>} - Array of place predictions
 */
export const olaAutocomplete = async (input) => {
  if (!input || input.length < 3) return []
  try {
    const res = await fetch(
      `${OLA_BASE}/autocomplete?input=${encodeURIComponent(input)}&api_key=${OLA_API_KEY}`
    )
    if (!res.ok) throw new Error('Autocomplete failed')
    const data = await res.json()
    return (data.predictions || []).map(p => ({
      placeId: p.place_id,
      description: p.description,
      mainText: p.structured_formatting?.main_text || p.description,
      secondaryText: p.structured_formatting?.secondary_text || '',
      lat: p.geometry?.location?.lat || null,
      lng: p.geometry?.location?.lng || null,
    }))
  } catch (e) {
    console.error('Ola Autocomplete error:', e)
    return []
  }
}

/**
 * Geocode - convert address text to coordinates
 * @param {string} address - Address string
 * @returns {Promise<Object|null>} - { lat, lng, formatted_address }
 */
export const olaGeocode = async (address) => {
  try {
    const res = await fetch(
      `${OLA_BASE}/geocode?address=${encodeURIComponent(address)}&api_key=${OLA_API_KEY}`
    )
    if (!res.ok) throw new Error('Geocode failed')
    const data = await res.json()
    const result = data.geocodingResults?.[0]
    if (!result) return null
    return {
      lat: result.geometry?.location?.lat,
      lng: result.geometry?.location?.lng,
      formatted_address: result.formatted_address,
    }
  } catch (e) {
    console.error('Ola Geocode error:', e)
    return null
  }
}

/**
 * Reverse Geocode - convert coordinates to address
 * @param {number} lat
 * @param {number} lng
 * @returns {Promise<Object|null>} - { formatted_address, components }
 */
export const olaReverseGeocode = async (lat, lng) => {
  try {
    const res = await fetch(
      `${OLA_BASE}/reverse-geocode?latlng=${lat},${lng}&api_key=${OLA_API_KEY}`
    )
    if (!res.ok) throw new Error('Reverse geocode failed')
    const data = await res.json()
    const result = data.results?.[0]
    if (!result) return null
    const components = {}
    ;(result.address_components || []).forEach(c => {
      if (c.types?.includes('sublocality') || c.types?.includes('sublocality_level_1')) components.area = c.long_name
      if (c.types?.includes('locality')) components.city = c.long_name
      if (c.types?.includes('administrative_area_level_1')) components.state = c.long_name
      if (c.types?.includes('postal_code')) components.pincode = c.long_name
    })
    return {
      formatted_address: result.formatted_address,
      components,
    }
  } catch (e) {
    console.error('Ola Reverse Geocode error:', e)
    return null
  }
}

/**
 * Nearby Search - find popular areas around coordinates
 * @param {number} lat
 * @param {number} lng
 * @param {string} type - e.g. 'restaurant', 'locality', 'sublocality'
 * @returns {Promise<Array>} - Array of nearby places
 */
export const olaNearbySearch = async (lat, lng) => {
  try {
    // Using a tighter radius and broader types for "real nearby" experience
    const res = await fetch(
      `${OLA_BASE}/nearbysearch?location=${lat},${lng}&radius=500&api_key=${OLA_API_KEY}`
    )
    if (!res.ok) throw new Error('Nearby search failed')
    const data = await res.json()
    const results = data.predictions || data.results || []
    
    return results.slice(0, 6).map(p => ({
      placeId: p.place_id || p.reference,
      name: p.structured_formatting?.main_text || p.name || p.description,
      city: p.structured_formatting?.secondary_text?.split(',').shift()?.trim() || '',
      lat: p.geometry?.location?.lat || lat,
      lng: p.geometry?.location?.lng || lng,
    }))
  } catch (e) {
    console.error('Ola Nearby Search error:', e)
    return []
  }
}
