using System;
using System.Collections.Specialized;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Net;
using System.Text;
using Nancy;
using Nancy.Conventions;

namespace Customer.InPay.Sample
{
    public class ApplicationBootstrapper : DefaultNancyBootstrapper
    {
        protected override void ConfigureConventions(NancyConventions nancyConventions)
        {
            nancyConventions.StaticContentsConventions.Add(StaticContentConventionBuilder.AddDirectory("Static", @"Static"));
            base.ConfigureConventions(nancyConventions);
        }
    }

    public class SampleModule : NancyModule
    {
        private const string GateUrl = "https://sandbox.payler.com/gapi/";
        private const string MerchantKey = "YOUR_MERCHANT_KEY";
        private const string Password = "YOUR_MERCHANT_PASSWORD";

        private static string _lastOrderId = "";
        private const string PayPageTemplate = "";
        private const bool CreateRecurrentTemplate = false;
        private const string Language = "ru";
        private const string Currency = null;
        private const bool SendPayPageExtraParams = true;

        public SampleModule()
        {
            Get["/"] = _ => View["Index"];

            Get["/StartSession/{type}-{amount}"] = _ =>
            {
                var iAmount = Int32.Parse(_.amount);
                var sOrderId = Guid.NewGuid().ToString();
                SampleModule._lastOrderId = sOrderId;
                return this.GetResponse(String.Concat(SampleModule.GateUrl, "StartSession"), this.GetStartSessionParams(_.type, iAmount, sOrderId));
            };

            Get["/Complete-order_id={value}"] = _ =>
            {
                var response = this.GetResponse(String.Concat(SampleModule.GateUrl, "GetStatus"), this.GetStatusParams(_.value));
                var actionGetResponseStream = response.Contents;
                using (var stream = new MemoryStream())
                {
                    actionGetResponseStream(stream);
                    stream.Position = 0;
                    using (var streamReader = new StreamReader(stream))
                    {
                        Debug.Print(streamReader.ReadToEnd());
                    }
                }

                return View["Index"];
            };

            Get["/Charge/{amount}"] = _ =>
            {
                var iAmount = Int32.Parse(_.amount);
                return this.GetResponse(String.Concat(SampleModule.GateUrl, "Charge"), this.GetChargeParams(iAmount, SampleModule._lastOrderId));
            };

            Get["/Retrieve/{amount}"] = _ =>
                {
                    var iAmount = Int32.Parse(_.amount);
                    return this.GetResponse(String.Concat(SampleModule.GateUrl, "Retrieve"), this.GetRetrieveParams(iAmount, SampleModule._lastOrderId));
                };

            Get["/Refund/{amount}"] = _ =>
            {
                var iAmount = Int32.Parse(_.amount);
                return this.GetResponse(string.Concat(SampleModule.GateUrl, "Refund"), this.GetRefundParams(iAmount, SampleModule._lastOrderId));
            };

            Get["/GetStatus/"] = _ =>
            {
                return this.GetResponse(String.Concat(SampleModule.GateUrl, "GetStatus"), this.GetStatusParams(SampleModule._lastOrderId));
            };
        }

        private Response GetResponse(string address, NameValueCollection parameters)
        {
            try
            {
                ServicePointManager.SecurityProtocol = SecurityProtocolType.Tls11 | SecurityProtocolType.Tls12;
                var client = new WebClient();
                var response = client.UploadValues(address, "POST", parameters);

                return GetOkResponse(Encoding.UTF8.GetString(response));
            }
            catch (WebException e)
            {
                return GetErrorResponse(e);
            }
        }

        private Response GetOkResponse(string body, string contentType = "application/json")
        {
            var response = (Response) body;
            response.ContentType = contentType;
            response.StatusCode = Nancy.HttpStatusCode.OK;

            return response;
        }

        private Response GetErrorResponse(WebException e)
        {
            Response response;

            if (e.Response != null)
            {
                var sErrorBody = new StreamReader(e.Response.GetResponseStream()).ReadToEnd();
                response = (Response)sErrorBody;
                response.ContentType = e.Response.ContentType;
                if (((HttpWebResponse)e.Response).StatusCode == System.Net.HttpStatusCode.BadRequest)
                {
                    response.StatusCode = Nancy.HttpStatusCode.BadRequest;
                }
                else
                {
                    response.StatusCode = Nancy.HttpStatusCode.InternalServerError;
                }
            }
            else
            {
                response = (Response)"Ошибка: сервер шлюза не отвечает.";
                response.StatusCode = Nancy.HttpStatusCode.ServiceUnavailable;
            }

            return response;
        }

        private NameValueCollection GetStartSessionParams(string sessionType, int amount, string orderId)
        {
            var parameters = new NameValueCollection(7);
            parameters["type"] = sessionType;
            parameters["key"] = SampleModule.MerchantKey;
            parameters["order_id"] = orderId;
            parameters["amount"] = amount.ToString(new CultureInfo("en-US"));
            parameters["product"] = "Велосипед для программиста";
            parameters["total"] = "1";
            parameters["lang"] = Language;
            if (Currency != null)
            {
                parameters["currency"] = Currency;
            }
            parameters["template"] = PayPageTemplate;
            if (CreateRecurrentTemplate)
            {
                parameters["recurrent"] = "true";
            }
            if (SendPayPageExtraParams)
            {
                parameters["pay_page_param_user"] = "foo";
                parameters["pay_page_param_doc"] = "bar";
                parameters["pay_page_param_1"] = "baz";
            }
            return parameters;
        }

        private NameValueCollection GetChargeParams(int amount, string orderId)
        {
            var parameters = new NameValueCollection(4);
            parameters["key"] = SampleModule.MerchantKey;
            parameters["password"] = SampleModule.Password;
            parameters["order_id"] = orderId;
            parameters["amount"] = amount.ToString(new CultureInfo("en-US"));

            return parameters;
        }

        private NameValueCollection GetRetrieveParams(int amount, string orderId)
        {
            var parameters = new NameValueCollection(4);
            parameters["key"] = SampleModule.MerchantKey;
            parameters["password"] = SampleModule.Password;
            parameters["order_id"] = orderId;
            parameters["amount"] = amount.ToString(new CultureInfo("en-US"));

            return parameters;
        }

        private NameValueCollection GetRefundParams(int amount, string orderId)
        {
            var parameters = new NameValueCollection(4);
            parameters["key"] = SampleModule.MerchantKey;
            parameters["password"] = SampleModule.Password;
            parameters["order_id"] = orderId;
            parameters["amount"] = amount.ToString(new CultureInfo("en-US"));

            return parameters;
        }

        private NameValueCollection GetStatusParams(string orderId)
        {
            var parameters = new NameValueCollection(2);
            parameters["key"] = SampleModule.MerchantKey;           
            parameters["order_id"] = orderId;

            return parameters;
        }
    }
}