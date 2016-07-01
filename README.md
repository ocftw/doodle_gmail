# doodle_gmail
open monthly doodle poll and auto send email to your contacts. 

This project is fork from an awesome repo: [xperseguers/doodle_client](https://github.com/xperseguers/doodle_client)


如果你剛好需要一段固定的時間，用 [Doodle](http://doodle.com) 開啟有規律的日子、用 gmail 寄給同一群人投票，那 meeting.php 這個 command line script 可以幫你節省一些時間！


1. 開啟 google api 專案，下載 client_secret.json 到資料夾

2. `composer install`

3. 修改 meeting.php, members.json 輸入必要資訊

4. `php meeting.php`

5. 泡咖啡 have fun!


##### 在 meeting.php 中可以修改 google 預設 token 存放位置 `CREDENTIALS_PATH` 以及 serect `CLIENT_SECRET_PATH`


如果你剛好每次都需要用 google drive 開一個新的空白範本，然後將網址填入 google calendar 新活動並且邀請同一群人，那 done.php 這個 command line script 可以幫你節省一些時間！


1. 開啟 google api 專案，下載 client_secret.json 到資料夾

2. `composer install`

3. 修改 done.php, members.json 輸入必要資訊

4. `php done.php`

5. 泡咖啡 have fun!
