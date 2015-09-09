# set user time to America/Chicago
# export TZ='/usr/share/zoneinfo/America/Chicago'

# set colors
green='\e[0;32m'        # Green Foreground
red='\e[0;31m'          # Red Foreground
blue='\e[0;34m'         # Blue Foreground
black='\e[40m'          # Black Background
reset='\e[m'            # Color Reset

# Startup message
# get files and set to variables
itemsF=$(ls -lah /home/austin/public_html/var/import/Items.csv | awk '{print $5,$6,$7,$8}')
imagesF=$(ls -lah /home/austin/public_html/media/import/images.tar | awk '{print $5,$6,$7,$8}')
magmiL=$(ls -lah /home/austin/public_html/magmi/linkedretail/import/products/magmi_log | awk '{print $5,$6,$7,$8}')
ordersF=$(ls -lah /home/austin/public_html/magmi/linkedretail/export/orders/orders.csv | awk '{print $5,$6,$7,$8}')
# get dates and set to variables

currentDate=$(date +%b%e)
serverT=$(date)
itemDate=$(date -r /home/austin/public_html/var/import/Items.csv +%b%e)
imageDate=$(date -r /home/austin/public_html/var/import/Items.csv +%b%e)
magmiLDate=$(date -r /home/austin/public_html/magmi/linkedretail/import/products/magmi_log +%b%e)
ordersDate=$(date -r /home/austin/public_html/magmi/linkedretail/export/orders/orders.csv +%b%e)
# get uptime and display
sUptime=$(uptime)

echo -e "Server Status:$blue$sUptime$black$reset"
echo -e "Server Time:   $green$serverT$black$reset"
echo
echo "The latest LRSynch files:"
echo "----------------------------------"
if [ "$currentDate" == "$itemDate" ]; then
        echo -e "Items.csv:     $green$itemsF$black$reset"
else
    	echo -e "Items.csv:     $red$itemsF$black$reset"
fi
if [ "$currentDate" == "$imageDate" ]; then
        echo -e "Images.tar:    $green$imagesF$black$reset"
else
    	echo -e "Images.tar:    $red$imagesF$black$reset"
fi
echo "----------------------------------"
echo "The latest Magmi_Log:"
echo "----------------------------------"
if [ "$itemDate" == "$magmiLDate" ]; then
	echo -e "Magmi_Log:	$green$magmiL$black$reset"
else
	echo -e "Magmi_Log:	$red$magmiL$black$reset"
fi
echo "----------------------------------"
echo "The latest Orders.csv:"
echo "----------------------------------"
if [ "$currentDate" == "$ordersDate" ]; then
        echo -e "Orders.csv:    $green$ordersF$black$reset"
else
    	echo -e "Orders.csv:    $red$ordersF$black$reset"
fi
echo "----------------------------------"
