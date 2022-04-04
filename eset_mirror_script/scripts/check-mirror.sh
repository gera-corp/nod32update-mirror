#!/bin/bash
PRODUCTS=('EES' 'ESS')
URLS=('update.ver' 'eset_upd/update.ver')

if [ -n "$1" ]
then


for url in "${URLS[@]}"
do
echo "Check for url '$url'"
for prod in "${PRODUCTS[@]}"
do
        echo "Check Product $prod"
        for i in 3 4 5 6 7 8 9 10 11 12 13 14
        do
                HTTPCODE=`curl -o /dev/null -s -w "%{http_code}\n" -H "User-Agent: $prod Update (Windows; U; 32bit; VDB 47546; BPC $i.0.474.0;" $1/$url`;
                UPDATE=`curl -s -H "User-Agent: $prod Update (Windows; U; 32bit; VDB 47546; BPC $i.0.474.0; OS: 6.1.7601 SP 1.0 NT; TDB 47546;)" $1/$url | grep "file=" | head -1 | awk -F/ '{print $2}'`;
                echo "Checked version $i of product $prod: answer $HTTPCODE with path $UPDATE"
        done
done
done


else
echo "Set parameter url_address: ./check-mirror.sh http://nod32.dtkms.ru "
fi
