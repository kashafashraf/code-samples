using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using System.Security.Cryptography;
using Tech_Webservices;
using System.IO;
using System.Net;
using System.Net.Sockets;
using System.Threading;
using System.Data.Odbc;



namespace RequestDownload
{
    class DataDownload
    {
        // For creating Hashes
        static MD5 md_5 = new MD5CryptoServiceProvider();
        string username = "ACB";
        string password = "confidential";
        string salt = "confidential";
        string compcode = "confidential";
        static string generic_url = "soap_url";
        static string download_url = "soap_url";
        public static OdbcConnection db   = new OdbcConnection();

        // DataBase
        public static String conString =
            "DRIVER={MySQL ODBC 5.1 Driver};" +
            "SERVER=localhost;" +
            "DATABASE=test;" +
            "USER=root;" +
            "PASSWORD=;"; 


        public DataDownload()
        {
            db = new OdbcConnection(conString);
            try{
              db.Open();
            }
            catch(Exception e)
            {
                Console.WriteLine("- Error : -- Wasn't able open DB Connection");
            }
            
        }

        public void LogDataDB(ulong sid, string  int request_id, string logData, string msg_type)
        {

            String sql = @"INSERT into table(sid, hash, request_id, message, msg_type) VALUES( 
                                    " + sid +
                                    ",'" + hash + "'"+ 
                                    "," + request_id +
                                    ",'" + logData+"'"+
                                    ",'" + msg_type+"')";   

            OdbcCommand cmd = new OdbcCommand(sql, db);
            try
            {
                cmd.ExecuteNonQuery();
            }
            catch(Exception e)
            {
                Console.WriteLine("- Error : -- Wasn't able to add line to DB");
            }

        }


        public string getRotatingHash(string first_hash, ulong sid)
        {
            string hash = md5(first_hash + sid + salt);
            return hash;
        }


        public string cleanMessage(string str)
        {

            // Clean Data first, Remove first two Characters 
             str = str.Remove(0, 2);

            // Remove last 6 characters
            str = str.Remove(str.Length - 6);

            return str;
        }


        // 
        public string ConvertToHex(string asciiString)
        {
            string stringToHexString = Utilities.HexStringHelper.StringToBinHex(asciiString);
            return stringToHexString;
        }

        // 
        public string ConvertToAscii(string HexString)
        {
            string stringFromHexString = Utilities.HexStringHelper.BinHexToString(HexString);
            return stringFromHexString;
        }

        // 
        public Boolean canStartAuthentication(RequestDownload rd, ulong sid, string hash, int request_id)
        {
            // Start Request
            RequestType etype = RequestType.comms;
            EStartRequestResult RequestResult = rd.sq_startRequest(sid, hash, request_id, etype);

            // Finding if authentication can be done
            Boolean start_authentication = false;

            switch (RequestResult)
            {
                case EStartRequestResult.not_possible_at_this_time:
                    start_authentication = false;
                    break;
                case EStartRequestResult.pending_card_lock_please_wait:
                    start_authentication = false;
                    break;
                case EStartRequestResult.you_may_start_authentication:
                    start_authentication = true;
                    break;
            }

            return start_authentication;

        }

        // 
        public RequestDatagram getNextMsg(ulong sid, string hash, int request_id)
        {
            RequestDatagram rd = new RequestDatagram();
            try
            {
                rd = rd.sq_getNextMessage(sid, hash, request_id);
            }
            catch (Exception ex)
            {
                rd.cancelRequest(sid, hash, 4999, request_id);
            }

            return rd;
        }

        // 
        public void submitResponse(ulong sid, string hash, int request_id, string HexString)
        {

            // Convert HexString to Byte Array
            byte[] byteArrayFromHexString = Utilities.HexStringHelper.BinHexToByteArray(HexString);
            try
            {
                rd.submitMessage(sid, request_id, byteArrayFromHexString);
            }
            catch (Exception e)
            {
                rd.cancelRequest(sid, hash, 4999, request_id);
                Console.WriteLine("- Process : -- Error occurred. Cancelling current request " + request_id.ToString());
            }


        }

        //
        public static string md5(string s)
        {
            MemoryStream ms = new MemoryStream();
            StreamWriter sw = new StreamWriter(ms);
            sw.Write(s);
            sw.Flush();
            ms.Position = 0;
            byte[] b = md_5.ComputeHash(ms);
            string r = string.Format("{0:x2}{1:x2}{2:x2}{3:x2}{4:x2}{5:x2}{6:x2}{7:x2}{8:x2}{9:x2}{10:x2}{11:x2}{12:x2}{13:x2}{14:x2}{15:x2}",
            b[0], b[1], b[2], b[3], b[4], b[5], b[6], b[7],
            b[8], b[9], b[10], b[11], b[12], b[13], b[14], b[15]);
            ms.Close();
            return r;
        }

        //
        private void SendMessage(string message)
        {
            /* TcpClient tcpClient = (TcpClient) this.client;
             NetworkStream clientStream = tcpClient.GetStream();
             ASCIIEncoding encoder = new ASCIIEncoding();

             clientStream = tcpClient.GetStream();
             encoder = new ASCIIEncoding();
             byte[] buffer = encoder.GetBytes(message + "\r\n");
             Console.WriteLine("- Sent:     -- " + message);
             clientStream.Write(buffer, 0, buffer.Length);
             clientStream.Flush();*/

            TcpClient tcpclnt = new TcpClient();
            tcpclnt.Connect("10.102.98.34", 8200);
            Stream stm = tcpclnt.GetStream();
            ASCIIEncoding encoder = new ASCIIEncoding();
            byte[] buffer = encoder.GetBytes(message);
            stm.Write(buffer, 0, buffer.Length);
            stm.Flush();
            Console.WriteLine("- Sending : -- " + message);

        }

    }

}
