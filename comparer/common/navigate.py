from common.timeout import timeout
from common.files_utils import files_utils
from common.ui_utils import ui_utils
import os

class navigate():
    web_driver = None
    web_session = None
    ui_utils = None

    def __init__(self, web_session):
        self.web_driver = web_session.web_driver
        self.web_session = web_session
        self.ui_utils = ui_utils(web_session)

    def get(self, url, wait_for, dissapear=False, wait_seconds=None, dont_assert=False):
        files_utils.counter += 1
        self.web_session.logger.info("Navigate to URL {}".format(url))

        if "True" in self.web_session.DEBUG:
            files_utils.createScreenshot(self.web_session, "get-URL")

        if wait_seconds:
            secs = int(wait_seconds)
        else:
            secs = int(self.web_session.WAIT_LONG)
        with timeout(seconds= int(self.web_session.WAIT_LONG), error_message="Timed Navigating \"{}\"".format(url)):
            while True:
                try:
                    self.web_driver.get(url)
                    if dissapear:
                        if (dont_assert):
                            self.ui_utils.waitForTextOnPage(wait_for, int(
                                secs)), "Failed to find text '{}'".format(wait_for)
                        else:
                            assert self.ui_utils.waitForTextOnPage(wait_for, int(
                            secs)), "Failed to find text '{}'".format(wait_for)
                    else:
                        if (dont_assert):
                            self.ui_utils.waitForTextOnPage(wait_for, secs), "Failed to find text '{}'".format(wait_for)
                        else:
                            assert self.ui_utils.waitForTextOnPage(wait_for, secs), "Failed to find text '{}'".format(wait_for)

                    #self.web_session.logger.info("navigated")
                    self.ui_utils.closePossibleModalDialog()
                    break
                except:
                    if self.ui_utils.isTextOnPage("sorry, but something went wrong") or self.ui_utils.isTextOnPage("The server is temporarily unable"):
                        self.web_session.logger.info('Encountered "Sorry" message.')
                        self.ui_utils.sleep(5)
                        pass
                    else:
                        self.web_session.logger.error('Failed URL navigation')
                        raise
