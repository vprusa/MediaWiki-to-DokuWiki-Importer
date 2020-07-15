# Comparer

this dir contains scripts that creates screenshots of specific pages and saves them for further comparision.

Quite a lot of code was removed from this subproject because of non-public data (mainly handling SSO login and custom texts from conf/properties.properties also some hardcoded stuff).
If anybody will come across this I recommend use PyCharm's debug console and go step by step and replace all 'TODO'
```
grep -r 'TODO' ./
```
and some .*SSO.* properties in conf/properites.properties
