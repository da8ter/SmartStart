# TibberBestPriceDeviceStarter

This IP-Symcon module automatically switches a device on at the optimal (cheapest) electricity price time, based on an externally provided price variable.

## Features
- Calculates the best start time for a device based on price data
- Switches the device on at the calculated time (Boolean variable)
- Can be canceled at any time

## Setup
1. Import the module into IP-Symcon
2. In the configuration form:
   - Select the price variable (String, JSON)
   - Select the switch variable (Boolean)
   - Set runtime and finish time
3. Use the Boolean variable "Start Calculation" to start/cancel the process

## Notes
- The module only switches the device ON. Switching OFF must be handled externally.
- The price variable must contain data in the following format:
  ```json
  [{"start": 1745445600, "end": 1745449200, "price": 32.29}, ...]
  ```

## License
MIT License
