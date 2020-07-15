from  common.session import session
from common.base_login import base_login
from common.files_utils import files_utils
from selenium.webdriver.common.by import By
from pprint import pprint
from common.navigate import navigate
import datetime
import os
import traceback
import time
import sys
import shutil
import time

class run(object):

    ses = None

    oldDir = "old"
    newDir = "new"

    def getSessionAndLogin(self):
        if self.ses == None:
            self.ses = session()
            ses = self.ses
            if self.ses.FIREFOX_DOWNLOAD_DIR.startswith("./"):
                self.ses.FIREFOX_DOWNLOAD_DIR = os.path.dirname(os.path.realpath(__file__)) + "/" + self.ses.FIREFOX_DOWNLOAD_DIR
            files_utils.remove(self.ses, self.ses.FIREFOX_DOWNLOAD_DIR)
            os.mkdir(self.ses.FIREFOX_DOWNLOAD_DIR)
            downloadDir=self.ses.FIREFOX_DOWNLOAD_DIR+ "/last"

            downloadDirOld = self.ses.FIREFOX_DOWNLOAD_DIR + "/" + '{date:%Y-%m-%d_%H:%M:%S}'.format(date=datetime.datetime.now())

            if os.path.exists(downloadDir):
                dest = shutil.move(downloadDir, downloadDirOld)
            self.oldDir = downloadDir + "/screens/" + self.oldDir
            self.newDir = downloadDir + "/screens/" + self.newDir

            os.makedirs(downloadDir + '/debug/')
            os.makedirs(self.oldDir)
            os.makedirs(self.newDir)

            # TX_NOT_EMPTY_PAGE
            base_login(ses).login(ses.LOGIN_USERNAME, ses.LOGIN_PASSWORD, ses.TX_LOGIN, wait_for_TODO=ses.TX_TODO_SIGNIN)

    # TODO move "Diskuse" & "Navigace" => *.properties
    def handle(self, pageID, cnt):
        print("PageID {}: {}".format(cnt, pageID))
        emptyPageID="totallyUniquePage"

        def rreplace(s, old, new, occurrence):
            li = s.rsplit(old, occurrence)
            return new.join(li)
        pageID = rreplace(pageID, ".txt","",-1)
        pageUrlNew =  "{}".format(self.ses.BASE_URL + "?id="+pageID)

        navigate(self.ses).get(pageUrlNew, wait_for=self.ses.TX_NOT_EMPTY_PAGE)

        ele = self.ses.web_driver.find_element("xpath", '//body')
        total_height = ele.size["height"] + 100
        self.ses.web_driver.set_window_size(900, total_height)
        navigate(self.ses).get(pageUrlNew, wait_for=self.ses.TX_NOT_EMPTY_PAGE)
        files_utils.createScreenshot(self.ses, label=pageID, location=self.newDir)
        pageUrlOld = "{}".format(self.ses.BASE_MEDIAWIKI_URL + pageID)

        # TODO removed non-public code with SSO login (contianed quickfix for handling wierd redirect)

        ele = self.ses.web_driver.find_element("xpath", '//body')
        total_height = ele.size["height"] + 100
        self.ses.web_driver.set_window_size(900, total_height)
        navigate(self.ses).get(pageUrlOld, wait_for="Diskuse")

        files_utils.createScreenshot(self.ses, label=pageID, location=self.oldDir)
        # i navigate to not existing page so i would make sure next screen is on the right page
        navigate(self.ses).get("{}".format(self.ses.BASE_URL + "?id=" + emptyPageID),
            wait_for=self.ses.TX_EMPTY_PAGE)
        self.ses.ui_utils.waitForElementOnPage(By.XPATH, self.ses.TX_EMPTY_PAGE, self.ses.WAIT_EXTRA_SUPER_LONG, False)
        return cnt

    def magic(self):
        pprint("Logging in")
        self.getSessionAndLogin()
        pprint("Download desired pages")
        with open(self.ses.PAGE_IDS_FILE) as fp:
            cnt = 1
            pageID = fp.readline()
            while pageID:
                cnt = self.handle(pageID, cnt)
                #try:
                #    cnt = self.handle(pageID, cnt)
                #except:
                #    pprint("Error happend")
                #    pass
                cnt += 1
                pageID = fp.readline()

        pass
