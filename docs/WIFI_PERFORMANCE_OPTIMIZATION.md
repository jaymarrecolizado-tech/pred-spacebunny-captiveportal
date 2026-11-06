# Wi-Fi Performance Optimization Note

DICT Free Wi-Fi Customer Feedback Platform

Date: 2026-11-06

## 1. Purpose

This note recommends practical ways to improve the speed and reliability of the free Wi-Fi received by clients. It is based on the current monitoring available in the platform: the controller reports per-client radio rates and signal strength, but it does **not** report measured internet throughput.

In plain terms:

- `AP → Device` means the access point sending to the client.
- `Device → AP` means the client sending to the access point.
- These are negotiated radio link rates, not proof of actual internet speed.
- A single snapshot cannot show traffic used or historical performance.

## 2. What the current data already shows

Observed across 193 monitored sites:

- 665 wireless clients were visible during the sample.
- Most devices negotiate healthy rates (hundreds of Mbps).
- Some devices are stuck at very low rates, including the 802.11 basic rate of 6 Mbps.
- Several clients show strong asymmetry, for example 720.6 Mbps in one direction and 6.0 Mbps in the other.
- 26 wireless rows had a zero transmit rate in the sample.
- Maximum observed rates were 816.7 Mbps down to a device and 866.0 Mbps from a device.
- Wired clients do not report a wireless link rate, so they appear as 0 Mbps even when they are active.

This pattern is consistent with radio and coverage problems, not only internet bandwidth problems.

## 3. Main factors affecting speed and reliability

### 3.1 Radio coverage and signal strength

- Low RSSI means the client and access point reduce the modulation rate.
- Walls, glass, metal, concrete, and long distances reduce signal quality.
- A client at -60 dBm may still work well; a client below about -70 dBm is more likely to negotiate a basic rate.
- Poor placement of access points creates dead zones and weak edges.

### 3.2 Client device capability

- Older phones and low-cost devices support fewer spatial streams.
- Some devices only support 2.4 GHz.
- Battery-saving modes can reduce radio performance.
- A phone with one antenna cannot use the full capacity of a multi-antenna access point.

### 3.3 Access point capacity

- Too many clients on one access point lowers airtime for everyone.
- A 2.4 GHz radio has less capacity and is more congested than 5 GHz.
- 20 MHz channels carry less data than 40 MHz or 80 MHz channels.
- Basic-rate clients consume disproportionate airtime.

### 3.4 Channel interference

- Neighbouring Wi-Fi networks on the same channel reduce throughput.
- Non-Wi-Fi devices can interfere with 2.4 GHz.
- Auto-channel selection can choose poor channels if neighbouring networks change.
- High transmit power can increase interference rather than improve coverage.

### 3.5 Backhaul and internet capacity

- The Wi-Fi radio can be fast while the internet link is slow.
- Shared or congested uplinks reduce all users’ experience.
- Poor DNS, proxy, or firewall configuration can make pages feel slow.
- Local content may be fast even when the internet is slow.

### 3.6 Access point hardware and firmware

- Old firmware may have bugs, poor roaming, or weak radio performance.
- Insufficient PoE power can reduce radio output or stability.
- Low-end access points may not handle many clients well.
- Poor antenna design or damaged hardware reduces coverage.

### 3.7 Configuration

- 2.4 GHz-only deployment limits speed.
- Band steering may not work if clients are sticky or unsupported.
- Disabled or poorly configured airtime fairness can let one slow client dominate the channel.
- No QoS means background traffic can affect interactive traffic.
- Excessive SSIDs reduce available airtime.

### 3.8 Client behaviour

- Sticky clients may stay connected to a weak access point instead of roaming.
- Background uploads, cloud sync, and video calls consume bandwidth.
- Multiple devices per person increase load.

### 3.9 Environmental conditions

- Weather, construction, metal structures, and reflective surfaces can change RF behaviour.
- Temporary events can create sudden client density.

## 4. Recommended solutions

### A. Quick wins, low cost

1. Enable or improve 5 GHz usage.
   - Prefer 5 GHz for capable devices.
   - Keep 2.4 GHz for legacy devices only where needed.
   - Use band steering if supported.

2. Improve channel planning.
   - Use non-overlapping 2.4 GHz channels: 1, 6, 11.
   - Use 5 GHz channels with adequate separation.
   - Avoid maximum channel width where interference is high.
   - Re-survey after changes.

3. Tune transmit power.
   - Reduce power where access points overlap too much.
   - Increase power only where coverage is genuinely missing.
   - Aim for smooth cell overlap, not maximum range.

4. Enable airtime fairness.
   - Prevent slow clients from consuming excessive channel time.
   - Monitor whether very old devices are disconnected more often.

5. Update firmware and reboot on a controlled schedule.
   - Apply tested firmware updates.
   - Reboot access points during low-usage windows.
   - Record changes and rollback plans.

6. Review SSID count.
   - Remove unused SSIDs.
   - Avoid separate SSIDs for every minor use case.
   - Use VLANs where supported instead of extra SSIDs.

7. Add basic QoS.
   - Prioritize DNS, captive portal, and interactive traffic.
   - Limit bulk or background traffic where policy allows.

### B. Capacity and coverage improvements

1. Add access points in weak or high-density areas.
   - Target areas with many simultaneous users.
   - Add APs where RSSI is consistently below -70 dBm.
   - Prefer wired backhaul for new APs.

2. Upgrade to Wi-Fi 6 or newer where justified.
   - Useful for high-density areas.
   - Improves efficiency and capacity.
   - Not a substitute for good coverage planning.

3. Use wider channels carefully.
   - 80 MHz can improve peak rates in clean RF environments.
   - 40 MHz or 20 MHz may be more stable in congested areas.
   - Validate with before-and-after monitoring.

4. Improve PoE and power budget.
   - Ensure switches provide enough power.
   - Avoid underpowered APs.

5. Replace failing or low-capability hardware.
   - Focus on APs with repeated errors or poor client counts.
   - Keep a spare-parts and replacement plan.

### C. Backhaul and internet improvements

1. Measure the actual upstream link.
   - Test speed at the gateway, not only over Wi-Fi.
   - Identify whether the bottleneck is Wi-Fi or internet.

2. Upgrade internet capacity where usage justifies it.
   - Use measured peak demand, not assumed demand.
   - Plan for growth.

3. Improve DNS and local services.
   - Use reliable DNS resolvers.
   - Cache common content where policy allows.
   - Keep captive portal and survey pages lightweight.

4. Separate critical traffic.
   - Keep management traffic separate from public Wi-Fi.
   - Avoid sharing a congested uplink with other services.

### D. Monitoring and operations

1. Track radio health, not only link rates.
   - RSSI distribution.
   - Clients per access point.
   - Count of clients below a basic-rate threshold.
   - Failed association or authentication events.

2. Add scheduled sampling.
   - Poll client data on a schedule.
   - Store historical samples.
   - Calculate trends by site, AP, and time of day.

3. Measure actual throughput where possible.
   - Use cumulative traffic counters and delta calculation.
   - Use controlled speed tests from representative clients.
   - Compare Wi-Fi performance against gateway performance.

4. Create alert thresholds.
   - Too many clients on one AP.
   - Too many low-rate clients.
   - AP offline or unreachable.
   - Repeated authentication failures.

5. Keep change logs.
   - Record channel, power, firmware, and hardware changes.
   - Compare performance before and after each change.

### E. Policy and user-experience improvements

1. Set fair-use expectations.
   - Free Wi-Fi is best-effort.
   - Avoid promising guaranteed speeds.

2. Add per-client limits where appropriate.
   - Bandwidth caps can prevent one user from dominating an AP.
   - Time limits can improve availability in crowded locations.

3. Prioritize essential services.
   - Captive portal, DNS, and basic browsing should remain usable.
   - Heavy downloads may be deprioritized under load.

4. Improve user guidance.
   - Place QR posters in visible locations.
   - Tell users to move closer to the access point if slow.
   - Provide staff with a simple troubleshooting checklist.

## 5. Suggested implementation plan

### Phase 1: 0-30 days

- Audit AP placement, RSSI, client counts, channels, and firmware.
- Enable or tune band steering, airtime fairness, and channel planning.
- Reduce unnecessary SSIDs.
- Add basic QoS.
- Start tracking low-rate clients and clients per AP.
- Communicate best-effort service expectations.

### Phase 2: 31-90 days

- Upgrade or add APs in the worst sites.
- Improve backhaul where measurements show congestion.
- Implement scheduled sampling and historical trend reporting.
- Add alerting for offline APs and high client counts.
- Replace failing hardware.

### Phase 3: 91-180 days

- Expand Wi-Fi 6 or newer hardware where density justifies it.
- Refine QoS and fairness policies.
- Review user feedback and complaint patterns.
- Decide whether full throughput monitoring is worth the added complexity.

## 6. Success measures

Track these before and after changes:

- Percentage of wireless clients below 12 Mbps.
- Average and median AP-to-device link rate.
- Average and median device-to-AP link rate.
- Number of clients per access point.
- RSSI distribution, especially below -70 dBm.
- Number of sites with repeated low-rate clusters.
- User complaints about slow Wi-Fi.
- Captive portal completion rate.

## 7. Important caution

Do not present link rates as measured internet speed. Link rates describe the radio connection between a device and an access point. They can be high while the internet is slow, and they can be low because of distance, device capability, or interference rather than lack of internet bandwidth.

## 8. Recommended next decision

Choose one of these:

1. Run a radio and coverage audit first, then tune configuration.
2. Upgrade the worst-performing sites first.
3. Build historical throughput monitoring before making hardware changes.

Recommended starting point: **Option 1**, because it is the lowest-cost way to identify the real cause before spending on hardware.