#CONSTANTS
LOG_DIR=/var/log/httpd
HTTP_DIR=/var/www/html
TODAY=$(date +%d\\/%b\\/%Y)
WEEK=$(date --date "1 week ago" +%d\\/%b\\/%Y)
MONTH=$(date --date "1 month ago" +%d\\/%b\\/%Y)
FILES=`ls -tr $LOG_DIR/access*`

#HOUSEKEEPING
cd $HTTP_DIR
rm -rf stats
mkdir tmp
mkdir stats
mkdir stats/today
mkdir stats/week
mkdir stats/month
mkdir stats/alltime

#CONCATENATE LOGS
for f in $FILES 
do
 cat $f >> tmp/apache_log
done

#CREATE TEMP FILES
sed -n "/$TODAY/,$ p" tmp/apache_log | grep 'HTTP/1.1" 200' > tmp/daily_log
sed -n "/$TODAY/,$ p" tmp/apache_log | grep -v 'HTTP/1.1" 200' > tmp/daily_errors
sed -n "/$WEEK/,$ p" tmp/apache_log | grep 'HTTP/1.1" 200' > tmp/weekly_log
sed -n "/$WEEK/,$ p" tmp/apache_log | grep -v 'HTTP/1.1" 200' > tmp/weekly_errors
sed -n "/$MONTH/,$ p" tmp/apache_log | grep 'HTTP/1.1" 200' > tmp/monthly_log
sed -n "/$MONTH/,$ p" tmp/apache_log | grep -v 'HTTP/1.1" 200' > tmp/monthly_errors
cat tmp/apache_log | grep 'HTTP/1.1" 200' > tmp/alltime_log
cat tmp/apache_log | grep -v 'HTTP/1.1" 200' > tmp/alltime_errors

#TODAY
cat tmp/daily_log | sed 's,^[[:print:]]*"GET,,' | sed 's, HTTP/1.1" 200 [[:print:]]*$,,' | sort | uniq -c | sort -gr > stats/today/requests
echo 'Daily summary: '$(date +%d\ %b\ %Y) > stats/today/summary
echo 'Successful requests: ' `cat tmp/daily_log | wc -l` >> stats/today/summary
echo 'Unique requests: ' `cat stats/today/requests | wc -l` >> stats/today/summary
echo 'Unsuccessful requests: ' `cat tmp/daily_errors | wc -l` >> stats/today/summary
echo 'Requests by path: ' >> stats/today/summary
cat tmp/daily_log | sed 's,^[[:print:]]*"GET,,' | sed 's, HTTP/1.1" 200 [[:print:]]*$,,' | sed 's,?[[:print:]]*$,,' | sed 's,\.[[:print:]]*$,,' | sort | uniq -c | sort -gr >> stats/today/summary
cat tmp/daily_log | sed 's, - - [[:print:]]*,,' | sort | uniq -c | sort -gr > stats/today/clients
echo 'Unique IPs (clients): ' `cat tmp/daily_log | sed 's, - - [[:print:]]*,,' | sort | uniq | wc -l` >> stats/today/summary
echo 'WHOIS Info'  >> stats/today/summary
for ip in `cat stats/today/clients | sed 's,[[:space:]]*[0-9]*[[:space:]]*,,'`
do
 whois $ip | egrep 'country|descr' -m 1 | sed 's,country:[[:space:]]*,,' | sed 's,descr:[[:space:]]*,,' | sed 'N; s/\n/ , /' >> tmp/organisations
done
cat tmp/organisations | sort | uniq | sed 's/^/	/'>> stats/today/summary
cat stats/today/summary

#WEEK
cat tmp/weekly_log | sed 's,^[[:print:]]*"GET,,' | sed 's, HTTP/1.1" 200 [[:print:]]*$,,' | sort | uniq -c | sort -gr > stats/week/requests
echo 'Weekly summary: '$(date --date "1 week ago" +%d\ %b\ %Y) - $(date +%d\ %b\ %Y) > stats/week/summary
echo 'Successful requests: ' `cat tmp/weekly_log | wc -l` >> stats/week/summary
echo 'Unique requests: ' `cat stats/week/requests | wc -l` >> stats/week/summary
echo 'Unsuccessful requests: ' `cat tmp/weekly_errors | wc -l` >> stats/week/summary
echo 'Requests by path: ' >> stats/week/summary
cat tmp/weekly_log | sed 's,^[[:print:]]*"GET,,' | sed 's, HTTP/1.1" 200 [[:print:]]*$,,' | sed 's,?[[:print:]]*$,,' | sed 's,\.[[:print:]]*$,,' | sort | uniq -c | sort -gr >> stats/week/summary
cat tmp/weekly_log | sed 's, - - [[:print:]]*,,' | sort | uniq -c | sort -gr > stats/week/clients
echo 'Unique IPs (clients): ' `cat tmp/weekly_log | sed 's, - - [[:print:]]*,,' | sort | uniq | wc -l` >> stats/week/summary
rm -f tmp/organisations
echo 'WHOIS Info'  >> stats/week/summary
for ip in `cat stats/week/clients | sed 's,[[:space:]]*[0-9]*[[:space:]]*,,'`
do
 whois $ip | egrep 'country|descr' -m 1 | sed 's,country:[[:space:]]*,,' | sed 's,descr:[[:space:]]*,,' | sed 'N; s/\n/ , /' >> tmp/organisations
done
cat tmp/organisations | sort | uniq | sed 's/^/	/'>> stats/week/summary
cat stats/week/summary

#MONTH
cat tmp/monthly_log | sed 's,^[[:print:]]*"GET,,' | sed 's, HTTP/1.1" 200 [[:print:]]*$,,' | sort | uniq -c | sort -gr > stats/month/requests
echo 'Monthly summary: '$(date --date "1 month ago" +%d\ %b\ %Y) - $(date +%d\ %b\ %Y) > stats/month/summary
echo 'Successful requests: ' `cat tmp/monthly_log | wc -l` >> stats/month/summary
echo 'Unique requests: ' `cat stats/month/requests | wc -l` >> stats/month/summary
echo 'Unsuccessful requests: ' `cat tmp/monthly_errors | wc -l` >> stats/month/summary
echo 'Requests by path: ' >> stats/month/summary
cat tmp/monthly_log | sed 's,^[[:print:]]*"GET,,' | sed 's, HTTP/1.1" 200 [[:print:]]*$,,' | sed 's,?[[:print:]]*$,,' | sed 's,\.[[:print:]]*$,,' | sort | uniq -c | sort -gr >> stats/month/summary
cat tmp/monthly_log | sed 's, - - [[:print:]]*,,' | sort | uniq -c | sort -gr > stats/month/clients
echo 'Unique IPs (clients): ' `cat tmp/monthly_log | sed 's, - - [[:print:]]*,,' | sort | uniq | wc -l` >> stats/month/summary
rm tmp/organisations
echo 'WHOIS Info'  >> stats/month/summary
for ip in `cat stats/month/clients | sed 's,[[:space:]]*[0-9]*[[:space:]]*,,'`
do
 whois $ip | egrep 'country|descr' -m 1 | sed 's,country:[[:space:]]*,,' | sed 's,descr:[[:space:]]*,,' | sed 'N; s/\n/ , /' >> tmp/organisations
done
cat tmp/organisations | sort | uniq | sed 's/^/ /'>> stats/month/summary
cat stats/month/summary

#ALLTIME
cat tmp/alltime_log | sed 's,^[[:print:]]*"GET,,' | sed 's, HTTP/1.1" 200 [[:print:]]*$,,' | sort | uniq -c | sort -gr > stats/alltime/requests
echo 'Summary since: ' `head -1 tmp/alltime_log | sed 's,^[[:print:]]*\[,,' | sed 's,:[[:print:]]*$,,' | sed 's,/, ,g'` > stats/alltime/summary
echo 'Successful requests: ' `cat tmp/alltime_log | wc -l` >> stats/alltime/summary
echo 'Unique requests: ' `cat stats/alltime/requests | wc -l` >> stats/alltime/summary
echo 'Unsuccessful requests: ' `cat tmp/alltime_errors | wc -l` >> stats/alltime/summary
echo 'Requests by path: ' >> stats/alltime/summary
cat tmp/alltime_log | sed 's,^[[:print:]]*"GET,,' | sed 's, HTTP/1.1" 200 [[:print:]]*$,,' | sed 's,?[[:print:]]*$,,' | sed 's,\.[[:print:]]*$,,' | sort | uniq -c | sort -gr >> stats/alltime/summary
cat tmp/alltime_log | sed 's, - - [[:print:]]*,,' | sort | uniq -c | sort -gr > stats/alltime/clients
echo 'Unique IPs (clients): ' `cat tmp/alltime_log | sed 's, - - [[:print:]]*,,' | sort | uniq | wc -l` >> stats/alltime/summary
rm tmp/organisations
echo 'WHOIS Info'  >> stats/alltime/summary
for ip in `cat stats/alltime/clients | sed 's,[[:space:]]*[0-9]*[[:space:]]*,,'`
do
 whois $ip | egrep 'country|descr' -m 1 | sed 's,country:[[:space:]]*,,' | sed 's,descr:[[:space:]]*,,' | sed 'N; s/\n/ , /' >> tmp/organisations
done
cat tmp/organisations | sort | uniq | sed 's/^/ /'>> stats/alltime/summary
cat stats/alltime/summary

#TIDY UP
rm -rf tmp
