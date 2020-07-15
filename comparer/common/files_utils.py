import shutil
import os
import codecs

class files_utils():

    counter=0

    def remove(ses, filePath):
        if os.path.isfile(filePath):
            ses.logger.info("Remove old file " + filePath)
            os.remove(filePath)
        if os.path.isdir(filePath) :
            shutil.rmtree(filePath)

    def simpleStr(cstr):
        if not cstr:
            return ""
        cstr = str(cstr)
        return cstr.replace("http", "").replace("//", "").replace("/", "_").replace(" ","")

    def createScreenshot(ses, label, location = None):

        useUrl=ses.web_driver.current_url
        if location != None:
            downloadDir = location
            fileName = "screen-" + label + "-" + files_utils.simpleStr(useUrl)
        else:
            fileName = "screen-" + str(files_utils.counter) + "-" + label + "-" + files_utils.simpleStr(useUrl)
            downloadDir = ses.FIREFOX_DOWNLOAD_DIR_LAST + "/debug"
        ses.logger.info("FIREFOX_DOWNLOAD_DIR_LAST: " + ses.FIREFOX_DOWNLOAD_DIR_LAST )
        ses.logger.info("downloadDir: " + downloadDir)
        pageSourceFileName = fileName + ".html"
        fileName = fileName + ".png"
        filePath = downloadDir + "/" + fileName

        ses.logger.info("Creating screenshot of page " + useUrl + " and saving it as " + filePath)
        ses.web_driver.save_screenshot(filePath)
        # also save page source
        pageSourceFilePath=downloadDir + "/" + pageSourceFileName
        pageSourceFilePath = os.path.join(downloadDir, pageSourceFileName)
        file_object = codecs.open(pageSourceFilePath, "w", "utf-8")
        html = ses.web_driver.page_source
        file_object.write(html)
        file_object.close()
