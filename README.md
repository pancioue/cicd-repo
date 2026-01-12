首先要先了解，即使沒有docker，依然可以做CICD
但有無docker差異很大

| 面向            | 無 Docker | 有 Docker |
| -------------  | -------- | -------- |
| 部署一致性       | ❌        | ✅        |
| CI/CD 解耦      | ❌        | ✅        |
| 回滾            | ❌        | ✅        |
| 可重現性         | ❌        | ✅        |
| scale / cloud   | ❌        | ✅        |

0️⃣ 先給你一個總結版（先有全貌）

Docker 的價值不是「比較潮」，而是：

> 把「部署時會變動的東西」提前鎖死在 CI 階段，讓 CD 只做一件事：換版本

沒有 Docker → 部署是一連串命令
有 Docker → 部署是一個版本切換


有無 docker 差異
-------

1️⃣ 部署一致性（Deployment Consistency）
沒有 Docker 時，CD 通常在機器上做：
``` bash
git pull
composer install
npm install
npm run build
php artisan migrate
```

✅ 有 Docker 為什麼一致？

CI 階段 build image
image 是 immutable artifact
CD 只做：
``` bash
docker pull myapp:sha
docker run myapp:sha
```

2️⃣ CI/CD 解耦（Decoupling）
3️⃣ 回滾（Rollback）
❌ 沒 Docker 為什麼痛苦？

Rollback 你要：
 * git checkout 上一版
 * 還原 vendor
 * 還原 build assets
 * 重新跑部署流程

👉 你在「回到某個狀態」，但那個狀態已經不在了

✅ Docker 回滾為什麼秒殺？
因為你已經有：
``` bash
myapp:abc123
myapp:def456
```
Rollback =：
```bash
docker run myapp:abc123
```
👉 不重 build
👉 不重裝依賴
👉 不碰主機環境

這是「切版本」，不是「修環境」

## 初始環境
專案結構
```bash
your-repo/
  app/ ...
  docker/
    nginx/default.conf
  Dockerfile
  docker-compose.yml
  .github/workflows/
    ci-pr.yml
    ci-main.yml
    ci-release.yml
```

### ci-release.yml 
```
name: CI - Release (Build & Push Version + SHA)

on:
  release: # 當發佈一版時會執行
    types: [published]

...
```

開始一個基本的 laravel 專案
```bash
docker run --rm -u "$(id -u):$(id -g)" \
  -v "$PWD/app":/app -w /app \
  composer:2 \
  create-project laravel/laravel .
```


## 到 github 產生 GHCR token
GitHub → Settings → Developer settings → Personal access tokens
注意只能選擇 Token (classic)

這裡說的
https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry
GCP說建議用 Fine-grained token（新版）會找不到 `read:packages`、`write:packages`

## 登入 GHCR
在本機登入 GHCR（Docker）
```bash
docker login ghcr.io -u <你的 GitHub 使用者名稱>
```

接著 密碼貼上你的 PAT（不是 GitHub 帳號密碼）。
```bash
docker login ghcr.io -u panda-pan
Password: <貼上 PAT>
```
成功會看到：
```bash
Login Succeeded
```
### 測試
登入成功後可以先試試看一次最小推送驗證
```bash
docker build --build-arg APP_ENV=production -t ghcr.io/<OWNER>/<REPO>:local-test .
# docker build --build-arg APP_ENV=production -t ghcr.io/pancioue/cicd-repo:local-test .
```
然後 push：
```bash
docker push ghcr.io/<OWNER>/<REPO>:local-test
```

成功後，去repo 頁面右側看 Packages（或你的 GitHub profile → Packages），應該會出現剛 push 的 local-test。

若有看到上傳成功，表示剛才的 token 

## Build and push
修改 __ci-main.yml__
``` yml
name: CI - Main (Build & Push Image)

on:
  push:
    branches: ["main"]

permissions:
  contents: read
  packages: write

jobs:
  build-and-push:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Login to GHCR
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Compute tags
        id: meta
        run: |
          SHORT_SHA="${GITHUB_SHA::7}"
          echo "short_sha=$SHORT_SHA" >> $GITHUB_OUTPUT

      - name: Build and push
        uses: docker/build-push-action@v6
        with:
          context: .
          file: ./Dockerfile
          push: true
          build-args: |
            APP_ENV=production
          tags: |
            ghcr.io/pancioue/cicd-repo:latest
            ghcr.io/pancioue/cicd-repo:sha-${{ steps.meta.outputs.short_sha }}
```


## 情境1. 防止 merge時 build 失敗(ci-main.yml)
> 首先要注意的是，沒有按下 merge 後如果build失敗就不給合併這功能
> github 的 branch protection 實際上要用發 pr 時就要先 check build 會不會失敗
> 如果依據設定的 check 條件或 job 跑失敗，就不能按下 merge 鈕

最常見的標準做法：不要讓它 merge（用 branch protection）
理想狀態：CI 沒過，GitHub 不允許 merge。

做法是 GitHub 設定：

* Settings → Branches → Branch protection rules
* 勾選：
  - Require status checks to pass before merging
  - 選你的 workflow check（例如 CI - Main 或你之後的 test job）
    * 單人測試不用勾pr 需要approve
  - （可選）Require pull request reviews

這樣就不會有「merge 了才發現 build 壞」的情況。
- - - 
但如果已經 merge 進去了，而且 CI 真的炸了
常見兩種做法：
* 修很快（幾分鐘～半小時內能確定） → 直接 hotfix 修掉
* 不確定要查多久 / 影響範圍大 → 先 revert 讓 main 先活過來，再慢慢查

## 部署
可以先使用 Cloud Run(providing auto-scaling HTTP services)
![建立服務](/image/cloud_run/create_service.jpg)

這裡選擇 Cloud Build，以下是官方對 Cloud Build的說明

> 只要使用 Cloud Build 的持續部署功能，您就能將原始碼存放區的異動內容自動更新至 Artifact Registry 中的容器映像檔，並部署至 Cloud Run。
  您的程式碼應透過 $PORT 監聽 HTTP 要求，存放區中則須包含 Dockerfile 或 Go、Node.js、Python、Java、.NET Core 或 Ruby 的原始碼，以便建構到容器映像檔中。

這是官方對於 Cloud Build 的說明，簡單來說
* Cloud Build「負責部署」
* Cloud Run「負責上線後服務」

### 設定 Cloud Build 持續部署功能
![Cloud Build 設定](/image/cloud_run/Cloud_Build_config.jpg)

會跟github要權限
![Cloug Build github auth](/image/cloud_run/Cloud_Build_github_auth.jpg)

下一步
![Cloud Build step 2](/image/cloud_run/Cloud_Build_step2.jpg)

剛設定好的時候，只要合進 main 就會觸發部署的狀況，
初始建置好像沒辦法設定這麼多，不過之後可以調整設定

### 部署完後 500 server error
這邊有兩個問題
* 需要設定環境變數
* GCP上查不到 log 

因為這兩個問題，其實算是同個問題，因為都是要靠設定環境變數解決
首先 error 500 的原因是因為 Laravel 沒有環境變數 `.env`
而在 GCP 上的 _Logs Explorer_ 怎麼查都只有查到回覆 500，但沒有更清楚的 log
因為那是 request 的log，不是應用程式的 log
> 要看到真正錯誤，你的程式必須把錯誤輸出到：
   * stdout / stderr（Cloud Run 會自動收集）

#### 設定環境變數
在 Cloud Run 設定環境變數
1. GCP Console → Cloud Run
2. 點你的 service（cicd-repo）
3. 右上角 Edit & Deploy New Revision
4. 進到 Variables & Secrets（或叫「環境變數」）
5. 新增這些環境變數：
  * APP_ENV=production
  * APP_KEY=base64:你 local 的那一串
  * LOG_CHANNEL=stderr
  * LOG_LEVEL=debug
6. 送出部署（Deploy）

部署完後再打一次首頁，去 Logs 就應該會看到 run.googleapis.com/stderr 出現 Laravel 的錯誤堆疊。

#### 如果你的 Laravel 沒有 stderr channel
在 `config/logging.php` 加一個 channel
```php
'channels' => [
    // ...原本的

    'stderr' => [
        'driver' => 'monolog',
        'handler' => Monolog\Handler\StreamHandler::class,
        'with' => [
            'stream' => 'php://stderr',
        ],
        'level' => env('LOG_LEVEL', 'debug'),
    ],
],
```

#### database 環就變數
改完以上之後應該還是會看到錯誤
`Database file at path [/var/www/database/database.sqlite] does not exist`

`php artisan migrate` 會產生 database.sqlite
因為cloud run環境沒有執行 php artisan migrate，所以不會有這檔案會出錯

* 新增環境變數
  `SESSION_DRIVER=cookie`
  測試用 cookie 即可

### Q & A
在部署的時候可能會有一瞬間，剛好 request 進來，而剛好正在部署新版本 這樣有沒有可能造成 request 錯誤？
有可能，但 Cloud Run 已經把「部署時切版本」這件事做得接近零停機
大致上是:
> Cloud Run 會先把「新請求」導向新 revision，同時讓舊 revision 把「已在處理中的請求」跑完；等舊 revision 沒流量、又空閒一段時間後，才會被關掉。

## rollback
### 情況一：部署「直接啟動失敗」👉 Cloud Run 會自動保護你
例如：
* container 起不來
* listen port 錯誤
* startup timeout
* image 拉不到
* entrypoint crash

這時候會發生：
❌ 新 Revision 狀態：Failed
✅ 舊 Revision 繼續接 100% 流量

❗️服務對外「不會中斷」
👉 這已經是自動 rollback 行為了

### 情況二：部署成功，但「邏輯有 bug」👉 不會自動 rollback（這是重點）
例如：
* API 回 500
* DB 連線錯
* Session / env 設錯
* Laravel migration 問題
* SQLite / MySQL 路徑錯誤

因為 Cloud Run 只看「容器層級健康」，不懂你的業務邏輯
- - -
手動回滾（實務最常用）
步驟：
1. Cloud Run → 你的服務
2. 修訂版本（Revisions）
  ![rollback version](/image/rollback/rollback_version.jpg)
3. 找到上一個「Healthy / 成功」的 Revision
4. 將流量設為 100%
  ![rollback traffic](/image/rollback/rollback_traffic.jpg)
5. 儲存

👉 立即生效，幾乎零中斷

- - -
方式二：CLI 回滾
```
gcloud run services update-traffic YOUR_SERVICE \
  --to-revisions PREVIOUS_REVISION=100 \
  --region asia-east1
```


## 發佈時部署
> 如果不想要只要合進 main 就會觸發部署的狀況，可以調整為發佈時部署

![發佈時部署](/image/deploy_via_release/trigger_release.jpg)

![發佈版本標籤](/image/deploy_via_release/trigger_tag.jpg)

這樣一來當發布 `v.` 開頭的 tag 時就會部署  
值得一提的是，使用這個部署方式，`ci-release.yml`(release types: [published])
跟 Cloud Build 會同時觸發。這種情況下，`ci-release.yml` 似乎意義不大


## 手動部署
先到Cloud Build / 觸發條件 把剛才的觸發條件先停用
建立新的觸發條件選擇手動叫用
![手動叫用部署](/image/manul_deploy/manul_deploy_config.jpg)

UI手動建立似乎沒有法加入tag選項，預設是抓 latest 版號
(不過使用 CLI 應該可以指定 tag)
![manul_deploy](/image/manul_deploy/manul_deploy_choose_branch.jpg)

## 自定義 pipeline
* 這邊Cloud Build 設定欓選擇自定義的 cloudbuild.yaml，示範 Canary deployment + Smoke test
  👉 完整執行你定義的 pipeline
![自定義pipeline](/image/cloudbuild_pipeline/cloudbuild_pipeline.jpg)

新增了 cloudbuild.yaml 後就可以部署看看
- - -
為了讓 Cloud Build 可以讀取 GHCR.io 必須先設定 Artifact Registry
![artifact registry](/image/manul_deploy/artifact_registry.jpg)
上面大概是必須要填的欄位，其中驗證模式比較麻煩，
密鑰需要新增，這裡填上面的 GHCR 時得到的 key
![GHCR key](/image/manul_deploy/ghcr_key.jpg)


### cloudbuild.yaml
* 可以指定 Image 版本，這邊起初是用 latest 測試，不過這邊有個坑，可以參考下面
* 這份 `cloudbuild.yaml` 包含了 
  `Canary deployment + Smoke test`
* 當中有幾個步驟是打印 _route:list_ 與清除快取 _route:clear_，是中途debug用，不是必要的，不過就留著供日後參考用

### 容易誤入的坑
* cloudbuild.yaml中
  `_IMAGE: "asia-east1-docker.pkg.dev/<Project>/<Registry>/pancioue/cicd-repo:latest"`
  這種形式似乎不會正確去抓 latest 版本，起初的方向以為是 route cache，後來發現是 image 版本不對  
  儘管有設定 Artifact Registry(可以到Artifact->Project->Registry確認是否有設連結了)，
  必須使用GHCR的 digest，但要抓到 digest 也很麻煩，這份 cloudbuild.yaml是最後測試成功的版本
* 當遇到類似情況，可以先到 Cloud Run->service的修訂版本的檢查映像檔，是否是指定的版本
* 讓 cloudbuild可以存取 Secret Manager 密鑰存取者(在上面步驟中有建立的)
  > Cloud Build -> 權限 -> 下方 Secret Manager 密鑰存取者 啟用
* `--format='value(status.traffic[0].revisionName)'`
  當有兩個以上運組的版本，traffic[0]是抓第一個
  可以在 cloud shell 下這指令，查看相關欄位
  ``` shell
  gcloud run services describe "<repo-name>" \
    --region "asia-east1" \
    --format="json(status)"
  ```
* 不管怎麼試都打不通 `/healthz`，可能 cloud run有前面擋掉這路由

打通這裡很辛苦，不太優雅，
如果要使用自定義的 pipeline，可以試試直接上傳到 GCP的 Artifact Registry 可能會簡單點

### 名詞解釋
* Canary deployment
Canary deployment 是一種部署策略，
先讓「新版本」只接觸極少量或隔離的流量，
確認穩定後，再逐步或一次性切換成正式版本。

* Smoke Test
定義是：
  - 快速
  - 輕量
  - 驗證「服務有沒有起來、基本功能是否可用」
  - 通常是 /healthz, /up, /ping  

## Pub/Sub 訊息 是什麼時候用的
1️⃣ 非 Git 事件觸發部署
2️⃣ 跨系統、自動化流程
Pub/Sub 是 「當 Git 不夠用時」的進階解法

## 2) 加一個最基本的 Test Job（CI 才完整）

### 如果你想再快一點（進階）：用 BuildKit cache mount 讓 composer 下載快取留住（同一 runner/同一 cache 會更有感）。

## 版本會爆炸怎麼辦?
相對不重要，先記錄保留問題